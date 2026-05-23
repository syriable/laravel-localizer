<?php

declare(strict_types=1);

namespace Syriable\Localizer\Generator;

use Illuminate\Filesystem\Filesystem;
use Syriable\Localizer\Support\AtomicWriter;

/**
 * Reads and writes PHP translation files.
 *
 * Reading evaluates the existing PHP file to retrieve its returned array.
 * Writing renders the new array to PHP source code and persists it
 * atomically via `AtomicWriter`.
 */
final class TranslationFileRepository
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly AtomicWriter $writer,
        private readonly TranslationPhpRenderer $renderer,
    ) {}

    public function exists(string $absolutePath): bool
    {
        return $this->files->isFile($absolutePath);
    }

    /**
     * Reads and evaluates an existing PHP translation file.
     *
     * Returns an empty array when the file does not exist or does not
     * return an array.
     *
     * @return array<string, mixed>
     */
    public function read(string $absolutePath): array
    {
        if (! $this->files->isFile($absolutePath)) {
            return [];
        }

        $value = include $absolutePath;

        return is_array($value) ? $value : [];
    }

    /**
     * Renders `$data` to PHP source and writes it to `$absolutePath`.
     *
     * The parent directory is created if missing. Writes are atomic.
     *
     * @param array<string, mixed> $data
     */
    public function write(string $absolutePath, array $data): void
    {
        $contents = $this->renderer->render($data);

        $this->writer->write($absolutePath, $contents);
    }

    /**
     * Renders `$data` to PHP source without touching the filesystem.
     *
     * @param array<string, mixed> $data
     */
    public function preview(array $data): string
    {
        return $this->renderer->render($data);
    }
}
