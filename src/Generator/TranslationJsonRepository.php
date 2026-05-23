<?php

declare(strict_types=1);

namespace Syriable\Localizer\Generator;

use Illuminate\Filesystem\Filesystem;
use Syriable\Localizer\Support\AtomicWriter;

/**
 * Reads and writes JSON translation files (`lang/{locale}.json`).
 *
 * JSON translation files are flat key→value maps; there is no nesting.
 * Reading parses the JSON and returns an associative array. Writing
 * serialises the array back to pretty-printed JSON and persists it
 * atomically via `AtomicWriter`.
 */
final class TranslationJsonRepository
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly AtomicWriter $writer,
    ) {}

    /**
     * Reads and parses an existing JSON translation file.
     *
     * Returns an empty array when the file does not exist or contains
     * invalid JSON.
     *
     * @return array<string, mixed>
     */
    public function read(string $absolutePath): array
    {
        if (! $this->files->isFile($absolutePath)) {
            return [];
        }

        $content = $this->files->get($absolutePath);
        $data = json_decode($content, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Serialises `$data` to JSON and writes it to `$absolutePath`.
     *
     * The parent directory is created if missing. Writes are atomic.
     *
     * @param array<string, mixed> $data
     */
    public function write(string $absolutePath, array $data): void
    {
        $this->writer->write($absolutePath, $this->encode($data));
    }

    /**
     * Serialises `$data` to JSON without touching the filesystem.
     *
     * @param array<string, mixed> $data
     */
    public function preview(array $data): string
    {
        return $this->encode($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function encode(array $data): string
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return ($json !== false ? $json : '{}')."\n";
    }
}
