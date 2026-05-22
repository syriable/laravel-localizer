<?php

declare(strict_types=1);

namespace Syriable\Localizer\Support;

use Illuminate\Filesystem\Filesystem;
use Syriable\Localizer\Exceptions\LocalizerException;

/**
 * Computes content-addressable fingerprints for files.
 *
 * Uses xxh128 — a non-cryptographic hash that is fast, collision-resistant
 * at this scale, and deterministic. The hash is computed over the file's
 * raw byte contents. The choice of xxh128 is intentional: cache
 * invalidation is the only use case, so cryptographic strength would be
 * wasted CPU.
 */
final class FileHasher
{
    private const ALGORITHM = 'xxh128';

    public function __construct(private readonly Filesystem $files) {}

    /**
     * Returns the xxh128 hash of a file, prefixed with the algorithm name.
     *
     * Example return value: "xxh128:7a9c4e0f...".
     *
     * @throws LocalizerException If the file cannot be read.
     */
    public function hashFile(string $path): string
    {
        if (! $this->files->isFile($path)) {
            throw new LocalizerException("Cannot hash non-existent file [{$path}].");
        }

        $hash = @hash_file(self::ALGORITHM, $path);

        if ($hash === false) {
            throw new LocalizerException("Failed to compute hash for [{$path}].");
        }

        return self::ALGORITHM.':'.$hash;
    }

    /**
     * Returns the xxh128 hash of a string.
     */
    public function hashString(string $contents): string
    {
        return self::ALGORITHM.':'.hash(self::ALGORITHM, $contents);
    }
}
