<?php

declare(strict_types=1);

namespace Syriable\Localizer\Pipeline;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Syriable\Localizer\Contracts\ScanCache;
use Syriable\Localizer\Events\FileExtracted;
use Syriable\Localizer\Exceptions\LocalizerException;
use Syriable\Localizer\Support\ExtractorRegistry;

/**
 * Reads each fresh file and invokes its extractor.
 *
 * Files are processed sequentially — concurrency is a future concern
 * handled by swapping this stage. Each file's strings are collected
 * into a per-file list, recorded into the cache, appended to the
 * payload's freshStrings, and announced via {@see FileExtracted}.
 *
 * Read errors (deleted between discovery and extraction, permission
 * issues) are silently skipped — the engine prioritises completing the
 * scan over surfacing transient I/O issues; users see them in their
 * editor anyway.
 */
final class ExtractStrings
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly ExtractorRegistry $registry,
        private readonly ScanCache $cache,
        private readonly Dispatcher $events,
    ) {}

    public function handle(ScanPayload $payload, Closure $next): ScanPayload
    {
        foreach ($payload->freshFiles as $file) {
            if (! $this->files->isFile($file->absolutePath)) {
                continue;
            }

            try {
                $contents = $this->files->get($file->absolutePath);
            } catch (\Throwable) {
                // File-read errors are transient (file deleted between
                // discovery and read, permission flaps in CI). Skip and
                // continue — the user will see them in their editor.
                continue;
            }

            $extractor = $this->registry->get($file->extractor);

            $fileStrings = [];

            try {
                foreach ($extractor->extract($file, $contents) as $string) {
                    $fileStrings[] = $string;
                }
            } catch (\Throwable $cause) {
                // Extractor failures, unlike read errors, are not
                // transient — they indicate a bug in the extractor or
                // malformed source. Surface them with file context so
                // downstream tooling can report them clearly.
                throw LocalizerException::forFile($file, $cause);
            }

            $fingerprint = $payload->fingerprints[$file->absolutePath] ?? null;

            if ($fingerprint !== null && $payload->request->useCache) {
                $this->cache->store($file->absolutePath, $fingerprint, $fileStrings);
            }

            $payload->freshStrings = [...$payload->freshStrings, ...$fileStrings];

            $this->events->dispatch(new FileExtracted(
                file: $file,
                strings: $fileStrings,
                fromCache: false,
            ));
        }

        return $next($payload);
    }
}
