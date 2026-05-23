<?php

declare(strict_types=1);

namespace Syriable\Localizer\AI;

use Illuminate\Filesystem\Filesystem;
use Syriable\Localizer\Support\AtomicWriter;

/**
 * Persistent file-based cache for AI-generated translation values.
 *
 * Entries are keyed by a stable xxh128 hash of (model, sourceLocale,
 * targetLocale, text). Changing any of those parameters automatically
 * produces a different key, so stale entries are never served — they
 * are simply orphaned in the file until it is cleared manually.
 *
 * The backing file is a pretty-printed JSON object written atomically
 * to avoid corruption from concurrent writers.
 */
final class AiTranslationCache
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly AtomicWriter $writer,
        private readonly string $path,
    ) {}

    public function get(string $key): ?string
    {
        $data = $this->load();
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    public function set(string $key, string $value): void
    {
        $data = $this->load();
        $data[$key] = $value;
        $this->persist($data);
    }

    /**
     * Produces a cache key that is unique to the model + locale pair + text.
     */
    public function makeKey(string $model, string $sourceLocale, string $targetLocale, string $text): string
    {
        return hash('xxh128', implode('|', [$model, $sourceLocale, $targetLocale, $text]));
    }

    /**
     * @return array<string, string>
     */
    private function load(): array
    {
        if (! $this->files->exists($this->path)) {
            return [];
        }

        $contents = $this->files->get($this->path);
        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            return [];
        }

        $result = [];

        foreach ($decoded as $k => $v) {
            if (is_string($k) && is_string($v)) {
                $result[$k] = $v;
            }
        }

        return $result;
    }

    /**
     * @param array<string, string> $data
     */
    private function persist(array $data): void
    {
        $dir = dirname($this->path);

        if (! $this->files->isDirectory($dir)) {
            $this->files->makeDirectory($dir, 0o755, recursive: true);
        }

        $json = (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $this->writer->write($this->path, $json);
    }
}
