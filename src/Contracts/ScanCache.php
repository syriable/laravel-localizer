<?php

declare(strict_types=1);

namespace Syriable\Localizer\Contracts;

use Syriable\Localizer\Data\ExtractedString;

/**
 * Per-file fingerprint cache backing incremental scans.
 *
 * The cache stores, for each scanned file, the content fingerprint at the
 * time of extraction and the resulting strings. On subsequent scans the
 * engine compares the current fingerprint with the stored one; matches
 * load strings from cache, mismatches trigger re-extraction.
 *
 * Implementations MUST be safe to read concurrently. Writes are serialized
 * by the engine via Laravel's lock provider, so implementations need not
 * lock themselves.
 */
interface ScanCache
{
    /**
     * Returns the stored fingerprint for a path, or null if not cached.
     */
    public function fingerprint(string $absolutePath): ?string;

    /**
     * Returns the cached extracted strings for a path.
     *
     * Returns an empty array if the path is not cached. The cache does
     * not differentiate between "not cached" and "cached with zero
     * strings"; the {@see fingerprint()} method is the source of truth
     * for cache presence.
     *
     * @return list<ExtractedString>
     */
    public function load(string $absolutePath): array;

    /**
     * Stores the fingerprint and strings for a path.
     *
     * Any previously stored entry for the same path is replaced.
     *
     * @param iterable<ExtractedString> $strings
     */
    public function store(string $absolutePath, string $fingerprint, iterable $strings): void;

    /**
     * Removes the entry for a single path, if present.
     */
    public function forget(string $absolutePath): void;

    /**
     * Removes all cached entries.
     */
    public function flush(): void;

    /**
     * Persists pending writes to the underlying storage.
     *
     * Implementations that write eagerly may implement this as a no-op.
     */
    public function commit(): void;
}
