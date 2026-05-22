<?php

declare(strict_types=1);

namespace Syriable\Localizer\Pipeline;

use Closure;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Syriable\Localizer\Data\DiscoveredFile;
use Syriable\Localizer\Support\ExtractorRegistry;
use Syriable\Localizer\Support\PathMatcher;

/**
 * Walks the configured paths and produces a deterministic list of files.
 *
 * Files are discovered with Symfony Finder, then filtered by:
 *   1. Exclusion glob patterns (matched against the absolute path).
 *   2. Extractor resolution (files with no matching extractor dropped).
 *   3. If the request restricts extractors, only matching files survive.
 *
 * The output is sorted by absolute path to guarantee a deterministic
 * scan order — critical for reproducible CI runs and snapshot tests.
 */
final class DiscoverFiles
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly ExtractorRegistry $registry,
        private readonly PathMatcher $matcher,
    ) {}

    public function handle(ScanPayload $payload, Closure $next): ScanPayload
    {
        $request = $payload->request;

        $registry = $request->extractors !== null
            ? $this->registry->only($request->extractors)
            : $this->registry;

        $existingPaths = array_filter(
            $request->paths,
            fn (string $path): bool => $this->files->isDirectory($path) || $this->files->isFile($path),
        );

        $discovered = [];

        foreach ($existingPaths as $root) {
            if ($this->files->isFile($root)) {
                $this->considerFile($root, $root, $request->exclude, $registry, $discovered, $payload);

                continue;
            }

            $finder = (new Finder)
                ->in($root)
                ->files()
                ->ignoreDotFiles(true)
                ->ignoreVCS(true)
                ->followLinks();

            foreach ($finder as $file) {
                $this->considerFile($file->getRealPath(), $root, $request->exclude, $registry, $discovered, $payload);
            }
        }

        ksort($discovered);

        $payload->discoveredFiles = array_values($discovered);

        return $next($payload);
    }

    /**
     * @param list<string>                  $exclude
     * @param array<string, DiscoveredFile> $discovered
     */
    private function considerFile(
        string|false $absolute,
        string $root,
        array $exclude,
        ExtractorRegistry $registry,
        array &$discovered,
        ScanPayload $payload,
    ): void {
        if ($absolute === false || $absolute === '') {
            return;
        }

        if (isset($discovered[$absolute])) {
            return;
        }

        if ($this->matcher->matchesAny($absolute, $exclude)) {
            return;
        }

        $basename = basename($absolute);
        $extractor = $registry->resolveForBasename($basename);

        if ($extractor === null) {
            // Record the unmatched extension so callers can surface it.
            // We collect extensions, not paths, to keep memory bounded
            // even on huge trees full of unhandled file types.
            $ext = pathinfo($basename, PATHINFO_EXTENSION);

            if ($ext !== '') {
                $payload->skippedExtensions[$ext] = ($payload->skippedExtensions[$ext] ?? 0) + 1;
            }

            return;
        }

        $size = $this->files->size($absolute);
        $relative = $this->relativise($absolute, $root);

        $discovered[$absolute] = new DiscoveredFile(
            absolutePath: $absolute,
            relativePath: $relative,
            extension: pathinfo($absolute, PATHINFO_EXTENSION),
            extractor: $extractor->name(),
            size: max(0, $size),
        );
    }

    private function relativise(string $absolute, string $root): string
    {
        $rootReal = realpath($root);

        if ($rootReal !== false && str_starts_with($absolute, $rootReal)) {
            return ltrim(substr($absolute, strlen($rootReal)), DIRECTORY_SEPARATOR);
        }

        return $absolute;
    }
}
