<?php

declare(strict_types=1);

namespace Syriable\Localizer\Exceptions;

use Illuminate\Contracts\Cache\LockTimeoutException;
use RuntimeException;
use Syriable\Localizer\Data\DiscoveredFile;
use Throwable;

/**
 * Base exception for all errors raised by the engine.
 *
 * Provides named constructors that capture richer context (the offending
 * file, the cause exception, the cache schema version) so downstream
 * code can react meaningfully rather than parsing message strings.
 */
class LocalizerException extends RuntimeException
{
    /**
     * The file that caused this exception, when available.
     */
    private ?DiscoveredFile $contextFile = null;

    /**
     * The cache schema version mismatch, when applicable: [found, expected].
     *
     * @var array{0: int, 1: int}|null
     */
    private ?array $cacheVersionMismatch = null;

    /**
     * Wraps an exception raised while processing a specific file.
     *
     * The original exception is preserved as `$previous`, and the file
     * is attached via {@see file()} so handlers can include it in logs.
     */
    public static function forFile(DiscoveredFile $file, Throwable $cause): self
    {
        $message = sprintf(
            'Failed to process [%s]: %s',
            $file->absolutePath,
            $cause->getMessage(),
        );

        $exception = new self($message, 0, $cause);
        $exception->contextFile = $file;

        return $exception;
    }

    /**
     * Raised when a cache lock cannot be acquired within the configured
     * timeout. Wraps Laravel's {@see LockTimeoutException}
     * so callers only need to catch {@see LocalizerException}.
     */
    public static function lockTimeout(int $seconds, Throwable $cause): self
    {
        $message = sprintf(
            'Could not acquire the localizer scan lock within %d seconds. '
            .'Another scan may be running. Increase `localizer.lock_seconds` or '
            .'wait for the concurrent scan to finish.',
            $seconds,
        );

        return new self($message, 0, $cause);
    }

    /**
     * Raised when the on-disk cache schema version does not match the
     * version this build of the package writes.
     *
     * Consumers can catch this specifically to advise the user to re-run
     * with `--fresh`.
     */
    public static function cacheVersionMismatch(int $found, int $expected): self
    {
        $message = sprintf(
            'Localizer cache schema version mismatch: found v%d, expected v%d. '
            .'Run `php artisan localizer:scan --fresh` once to rebuild the cache.',
            $found,
            $expected,
        );

        $exception = new self($message);
        $exception->cacheVersionMismatch = [$found, $expected];

        return $exception;
    }

    /**
     * The file associated with this exception, if any.
     */
    public function file(): ?DiscoveredFile
    {
        return $this->contextFile;
    }

    /**
     * The cache schema version pair when this is a cache-version mismatch.
     *
     * @return array{0: int, 1: int}|null Tuple of [foundVersion, expectedVersion]
     */
    public function cacheVersions(): ?array
    {
        return $this->cacheVersionMismatch;
    }
}
