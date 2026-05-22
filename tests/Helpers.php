<?php

declare(strict_types=1);

namespace Syriable\Localizer\Tests;

/**
 * Recursively removes a directory and all its contents.
 *
 * Used by tests that create scratch directories under sys_get_temp_dir().
 *
 * Implementation note: PHPUnit installs a custom error handler that
 * captures `unlink()` / `rmdir()` warnings even when prefixed with `@`,
 * which marks tests as risky in strict mode. We therefore guard each
 * filesystem call with an explicit existence check so PHP never has
 * cause to emit a warning in the first place.
 */
function removeDir(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    $items = scandir($path);

    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $full = $path.DIRECTORY_SEPARATOR.$item;

        if (is_link($full)) {
            // Treat symlinks as files: remove the link, not the target.
            if (file_exists($full) || is_link($full)) {
                unlink($full);
            }

            continue;
        }

        if (is_dir($full)) {
            removeDir($full);

            continue;
        }

        if (file_exists($full)) {
            unlink($full);
        }
    }

    // Final rmdir is only safe if the directory still exists and is empty.
    if (is_dir($path)) {
        $remaining = scandir($path);
        if ($remaining !== false && count(array_diff($remaining, ['.', '..'])) === 0) {
            rmdir($path);
        }
    }
}

/**
 * Writes a file under $dir at the given relative path, creating any
 * missing parent directories.
 *
 * Returns the absolute path.
 */
function writeFile(string $dir, string $relative, string $contents = ''): string
{
    $path = $dir.DIRECTORY_SEPARATOR.ltrim($relative, '/\\');
    $parent = dirname($path);

    if (! is_dir($parent)) {
        mkdir($parent, 0o755, recursive: true);
    }

    file_put_contents($path, $contents);

    return $path;
}
