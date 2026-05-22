<?php

declare(strict_types=1);

namespace Syriable\Localizer\Cache;

use Illuminate\Filesystem\Filesystem;
use Syriable\Localizer\Contracts\ScanCache;
use Syriable\Localizer\Data\ExtractedString;
use Syriable\Localizer\Exceptions\LocalizerException;
use Syriable\Localizer\Support\AtomicWriter;

/**
 * JSON-on-disk implementation of the scan cache.
 *
 * Stores a single JSON document at the configured path. The document is
 * versioned so the cache can be safely evolved in future releases —
 * loading a document with an unrecognised version is treated as a cache
 * miss across the board (no corruption, no migration headaches).
 *
 * In-memory state is the source of truth during a scan; {@see commit()}
 * is the only operation that writes to disk, and it uses an
 * {@see AtomicWriter} to guarantee POSIX-atomic replacement.
 */
final class FileScanCache implements ScanCache
{
    /**
     * Schema version of the persisted cache file.
     *
     * Bump whenever the on-disk shape changes incompatibly. A version
     * mismatch is treated as a cache miss across the board — no
     * migration is attempted, and the next commit overwrites with the
     * current schema.
     *
     *   v1 — original beta schema (0.9.0): {group, namespace, ...}.
     *   v2 — 1.0.0 schema: {package, directories, file, key, ...}.
     */
    private const VERSION = 2;

    /**
     * @var array<string, array{fingerprint: string, strings: list<array<string, mixed>>}>
     */
    private array $entries = [];

    private bool $loaded = false;

    private bool $dirty = false;

    public function __construct(
        private readonly Filesystem $files,
        private readonly AtomicWriter $writer,
        private readonly string $path,
    ) {}

    public function fingerprint(string $absolutePath): ?string
    {
        $this->ensureLoaded();

        return $this->entries[$absolutePath]['fingerprint'] ?? null;
    }

    public function load(string $absolutePath): array
    {
        $this->ensureLoaded();

        $raw = $this->entries[$absolutePath]['strings'] ?? null;

        if ($raw === null) {
            return [];
        }

        /** @var list<ExtractedString> $strings */
        $strings = [];

        foreach ($raw as $item) {
            /** @var array{value: string, kind: string, extractor: string, location: array{path: string, line: int, column?: int}, package?: string|null, directories?: list<string>, file?: string|null, key?: string|null} $item */
            $strings[] = ExtractedString::fromArray($item);
        }

        return $strings;
    }

    public function store(string $absolutePath, string $fingerprint, iterable $strings): void
    {
        $this->ensureLoaded();

        $serialized = [];

        foreach ($strings as $string) {
            $serialized[] = $string->toArray();
        }

        $this->entries[$absolutePath] = [
            'fingerprint' => $fingerprint,
            'strings' => $serialized,
        ];

        $this->dirty = true;
    }

    public function forget(string $absolutePath): void
    {
        $this->ensureLoaded();

        if (! isset($this->entries[$absolutePath])) {
            return;
        }

        unset($this->entries[$absolutePath]);
        $this->dirty = true;
    }

    public function prune(array $knownPaths): void
    {
        $this->ensureLoaded();

        $known = array_flip($knownPaths);
        $before = count($this->entries);

        $this->entries = array_intersect_key($this->entries, $known);

        if (count($this->entries) !== $before) {
            $this->dirty = true;
        }
    }

    public function flush(): void
    {
        $this->entries = [];
        $this->loaded = true;
        $this->dirty = true;

        $this->commit();
    }

    public function commit(): void
    {
        if (! $this->dirty) {
            return;
        }

        $payload = [
            'version' => self::VERSION,
            'entries' => $this->entries,
        ];

        $encoded = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $this->writer->write($this->path, $encoded);

        $this->dirty = false;
    }

    /**
     * Returns the absolute path to the underlying cache file.
     */
    public function path(): string
    {
        return $this->path;
    }

    private function ensureLoaded(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;

        if (! $this->files->exists($this->path)) {
            return;
        }

        $raw = $this->files->get($this->path);

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($raw, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            // Corrupt cache: treat as empty. Next commit overwrites.
            return;
        }

        if (($decoded['version'] ?? null) !== self::VERSION) {
            // Unknown version: ignore.
            return;
        }

        if (! isset($decoded['entries']) || ! is_array($decoded['entries'])) {
            return;
        }

        /** @var array<string, array{fingerprint: string, strings: list<array<string, mixed>>}> $entries */
        $entries = $decoded['entries'];

        $this->entries = $entries;
    }

    /**
     * Returns the in-memory entry count. Test/debug helper.
     */
    public function count(): int
    {
        $this->ensureLoaded();

        return count($this->entries);
    }

    /**
     * Throws if the cache file exists but is unusable.
     *
     * Two failure modes are reported:
     *
     * - {@see LocalizerException::cacheVersionMismatch()} when the file's
     *   schema version is not the version this build writes. Catch this
     *   specifically to prompt the user to run `--fresh`.
     * - A generic {@see LocalizerException} when the file is unparseable
     *   JSON or otherwise corrupt.
     *
     * The default {@see ensureLoaded()} path is lenient — it treats both
     * conditions as cache misses and recovers silently. Use this method
     * only when you want a hard signal (e.g. health checks).
     */
    public function assertReadable(): void
    {
        if (! $this->files->exists($this->path)) {
            return;
        }

        $raw = $this->files->get($this->path);

        try {
            /** @var array{version?: int} $decoded */
            $decoded = json_decode($raw, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new LocalizerException("Cache file [{$this->path}] is corrupt: {$e->getMessage()}");
        }

        $version = $decoded['version'] ?? 0;

        if ($version !== self::VERSION) {
            throw LocalizerException::cacheVersionMismatch($version, self::VERSION);
        }
    }
}
