<?php

declare(strict_types=1);

namespace Syriable\Localizer\Data;

/**
 * The result of a single scan: the strings, plus diagnostics.
 *
 * Strings are returned in the order they were extracted. Use
 * {@see unique()} to deduplicate by fingerprint, or {@see groupedByKind()}
 * to bucket by short-key vs. JSON-key.
 */
final readonly class ScanResult
{
    /**
     * @param list<ExtractedString> $strings
     * @param array<string, int>    $skippedExtensions Extension → count of files skipped because no extractor matched.
     */
    public function __construct(
        public array $strings,
        public int $filesScanned,
        public int $filesFromCache,
        public float $durationMs,
        public array $skippedExtensions = [],
    ) {
        if ($filesScanned < 0) {
            throw new \InvalidArgumentException('filesScanned cannot be negative.');
        }

        if ($filesFromCache < 0) {
            throw new \InvalidArgumentException('filesFromCache cannot be negative.');
        }

        if ($filesFromCache > $filesScanned) {
            throw new \InvalidArgumentException(
                'filesFromCache cannot exceed filesScanned.',
            );
        }

        if ($durationMs < 0.0) {
            throw new \InvalidArgumentException('durationMs cannot be negative.');
        }
    }

    /**
     * The number of files freshly scanned this run (not loaded from cache).
     */
    public function filesFresh(): int
    {
        return $this->filesScanned - $this->filesFromCache;
    }

    /**
     * Returns a deduplicated list of strings, preserving first-occurrence order.
     *
     * @return list<ExtractedString>
     */
    public function unique(): array
    {
        $seen = [];
        $result = [];

        foreach ($this->strings as $string) {
            $fingerprint = $string->fingerprint();

            if (isset($seen[$fingerprint])) {
                continue;
            }

            $seen[$fingerprint] = true;
            $result[] = $string;
        }

        return $result;
    }

    /**
     * Buckets strings by their kind. Keys are the StringKind values.
     *
     * @return array{short_key: list<ExtractedString>, json_key: list<ExtractedString>}
     */
    public function groupedByKind(): array
    {
        $buckets = [
            StringKind::ShortKey->value => [],
            StringKind::JsonKey->value => [],
        ];

        foreach ($this->strings as $string) {
            $buckets[$string->kind->value][] = $string;
        }

        return $buckets;
    }

    /**
     * Buckets ShortKey strings by their target file name.
     *
     * Useful for downstream packages that write per-file PHP arrays:
     * each bucket key is a file name (e.g. `"pagination"`, `"auth"`,
     * `"buttons"`) and each value is the list of strings destined for
     * that file. JSON keys are excluded. Each bucket preserves
     * first-occurrence order.
     *
     * Note: this groups by file NAME only, not by the full directory
     * path. To bucket by the full filesystem target (including
     * directories and package), iterate the result and key on
     * `$string->filePath()` plus `$string->package`.
     *
     * @return array<string, list<ExtractedString>>
     */
    public function groupedByFile(): array
    {
        $buckets = [];

        foreach ($this->strings as $string) {
            if ($string->kind !== StringKind::ShortKey || $string->file === null) {
                continue;
            }

            $buckets[$string->file][] = $string;
        }

        return $buckets;
    }

    /**
     * Total number of strings in the result, including duplicates.
     */
    public function count(): int
    {
        return count($this->strings);
    }

    /**
     * @return array{
     *     strings: list<array{
     *         value: string,
     *         kind: string,
     *         extractor: string,
     *         location: array{path: string, line: int, column: int},
     *         package: string|null,
     *         directories: list<string>,
     *         file: string|null,
     *         key: string|null
     *     }>,
     *     filesScanned: int,
     *     filesFromCache: int,
     *     durationMs: float,
     *     skippedExtensions: array<string, int>
     * }
     */
    public function toArray(): array
    {
        return [
            'strings' => array_map(static fn (ExtractedString $s): array => $s->toArray(), $this->strings),
            'filesScanned' => $this->filesScanned,
            'filesFromCache' => $this->filesFromCache,
            'durationMs' => $this->durationMs,
            'skippedExtensions' => $this->skippedExtensions,
        ];
    }
}
