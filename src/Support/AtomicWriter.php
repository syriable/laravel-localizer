<?php

declare(strict_types=1);

namespace Syriable\Localizer\Support;

use Illuminate\Filesystem\Filesystem;
use Syriable\Localizer\Exceptions\LocalizerException;

/**
 * Writes file contents atomically.
 *
 * Strategy: write to a uniquely-named temporary file in the same directory
 * as the target, then `rename()` over the target. On POSIX systems
 * `rename()` is atomic when source and destination are on the same
 * filesystem, which is guaranteed here because both live in the same
 * directory. The parent directory is created if absent.
 */
final class AtomicWriter
{
    public function __construct(private readonly Filesystem $files) {}

    public function write(string $path, string $contents): void
    {
        $directory = \dirname($path);

        if (! $this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, mode: 0o755, recursive: true, force: true);
        }

        $temp = $path.'.'.bin2hex(random_bytes(6)).'.tmp';

        if ($this->files->put($temp, $contents) === false) {
            throw new LocalizerException("Failed to write temporary file [{$temp}].");
        }

        if (! @rename($temp, $path)) {
            @unlink($temp);

            throw new LocalizerException("Failed to atomically rename [{$temp}] to [{$path}].");
        }
    }
}
