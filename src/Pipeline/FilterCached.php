<?php

declare(strict_types=1);

namespace Syriable\Localizer\Pipeline;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Syriable\Localizer\Contracts\ScanCache;
use Syriable\Localizer\Events\FileExtracted;
use Syriable\Localizer\Support\FileHasher;

/**
 * Splits discovered files into "fresh" (need extraction) and "cached"
 * (load from cache) using content fingerprints.
 *
 * For each discovered file:
 *   - Compute the current xxh128 fingerprint.
 *   - Look up the stored fingerprint in the cache.
 *   - Match → load cached strings, append to cachedStrings.
 *   - Miss  → append the file to freshFiles, store the fingerprint
 *             (the fresh extraction stage records it later).
 *
 * Fires {@see FileExtracted} for cache hits so listeners can track
 * progress uniformly across cached and fresh files.
 */
final class FilterCached
{
    public function __construct(
        private readonly ScanCache $cache,
        private readonly FileHasher $hasher,
        private readonly Dispatcher $events,
    ) {}

    public function handle(ScanPayload $payload, Closure $next): ScanPayload
    {
        $useCache = $payload->request->useCache;

        foreach ($payload->discoveredFiles as $file) {
            $fingerprint = $this->hasher->hashFile($file->absolutePath);
            $payload->fingerprints[$file->absolutePath] = $fingerprint;

            if (! $useCache) {
                $payload->freshFiles[] = $file;

                continue;
            }

            $stored = $this->cache->fingerprint($file->absolutePath);

            if ($stored === $fingerprint) {
                $cachedStrings = $this->cache->load($file->absolutePath);

                $payload->cachedFiles[] = $file;
                $payload->cachedStrings = [...$payload->cachedStrings, ...$cachedStrings];

                $this->events->dispatch(new FileExtracted(
                    file: $file,
                    strings: $cachedStrings,
                    fromCache: true,
                ));

                continue;
            }

            $payload->freshFiles[] = $file;
        }

        return $next($payload);
    }
}
