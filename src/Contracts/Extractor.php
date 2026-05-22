<?php

declare(strict_types=1);

namespace Syriable\Localizer\Contracts;

use Syriable\Localizer\Data\DiscoveredFile;
use Syriable\Localizer\Data\ExtractedString;

/**
 * The contract every source-language extractor must implement.
 *
 * Extractors are stateless: the same instance is reused across many files,
 * and concurrent invocations are explicitly permitted. They MUST NOT
 * perform I/O — file contents are passed in. They MUST return a generator
 * or array of ExtractedString instances; they MUST NOT yield duplicates
 * within a single call (the pipeline handles cross-file deduplication).
 */
interface Extractor
{
    /**
     * Glob-style filename patterns this extractor matches.
     *
     * Patterns are matched against the basename of each file using
     * {@see fnmatch()}. Example: ['*.blade.php'].
     *
     * @return list<string>
     */
    public function patterns(): array;

    /**
     * The canonical name of this extractor (e.g. 'blade').
     *
     * Used for logging, the --extractor CLI flag, and the `extractor`
     * field on every produced {@see ExtractedString}.
     */
    public function name(): string;

    /**
     * Extract strings from a single file.
     *
     * @return iterable<ExtractedString>
     */
    public function extract(DiscoveredFile $file, string $contents): iterable;
}
