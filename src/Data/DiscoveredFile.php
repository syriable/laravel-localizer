<?php

declare(strict_types=1);

namespace Syriable\Localizer\Data;

/**
 * A file discovered during the scan, with its resolved extractor.
 *
 * `absolutePath` is the canonical filesystem path. `relativePath` is
 * relative to the scan root that surfaced it, used for exclusion matching
 * and human-readable output. `extractor` is the registered name (not the
 * class) of the extractor that will handle this file.
 */
final readonly class DiscoveredFile
{
    public function __construct(
        public string $absolutePath,
        public string $relativePath,
        public string $extension,
        public string $extractor,
        public int $size,
    ) {
        if ($absolutePath === '') {
            throw new \InvalidArgumentException('DiscoveredFile absolutePath cannot be empty.');
        }

        if ($extractor === '') {
            throw new \InvalidArgumentException('DiscoveredFile extractor name cannot be empty.');
        }

        if ($size < 0) {
            throw new \InvalidArgumentException('DiscoveredFile size cannot be negative.');
        }
    }
}
