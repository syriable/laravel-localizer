<?php

declare(strict_types=1);

namespace Syriable\Localizer\Pipeline;

use Closure;
use Syriable\Localizer\Contracts\ScanCache;

/**
 * Commits pending cache writes to disk.
 *
 * The cache implementation buffers stores in memory during a scan; this
 * stage flushes them at the very end so partial scans (interrupted by
 * Ctrl+C, fatal errors in extractors) do not corrupt the cache with
 * inconsistent state.
 *
 * When `useCache` is false on the request, the cache implementation
 * never received any stores, so {@see ScanCache::commit()} is a no-op
 * in that case.
 */
final class PersistCache
{
    public function __construct(private readonly ScanCache $cache) {}

    public function handle(ScanPayload $payload, Closure $next): ScanPayload
    {
        if ($payload->request->useCache) {
            $knownPaths = array_map(
                static fn ($f) => $f->absolutePath,
                $payload->discoveredFiles,
            );

            $this->cache->prune($knownPaths);
            $this->cache->commit();
        }

        return $next($payload);
    }
}
