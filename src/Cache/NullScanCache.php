<?php

declare(strict_types=1);

namespace Syriable\Localizer\Cache;

use Syriable\Localizer\Contracts\ScanCache;

/**
 * No-op cache implementation.
 *
 * Used when caching is disabled via config. Every {@see fingerprint()}
 * call returns null, ensuring every file is re-extracted on every scan.
 */
final class NullScanCache implements ScanCache
{
    public function fingerprint(string $absolutePath): ?string
    {
        return null;
    }

    public function load(string $absolutePath): array
    {
        return [];
    }

    public function store(string $absolutePath, string $fingerprint, iterable $strings): void
    {
        // Intentional no-op.
    }

    public function forget(string $absolutePath): void
    {
        // Intentional no-op.
    }

    public function flush(): void
    {
        // Intentional no-op.
    }

    public function commit(): void
    {
        // Intentional no-op.
    }
}
