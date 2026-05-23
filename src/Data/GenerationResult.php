<?php

declare(strict_types=1);

namespace Syriable\Localizer\Data;

/**
 * The outcome of a translation generation run.
 *
 * Mutable by design: the pipeline accumulates state as it processes each
 * file group, then returns this object to the caller.
 */
final class GenerationResult
{
    /** @var list<string> Absolute paths of files that were written (or would be written in a dry-run). */
    public array $written = [];

    /** @var list<string> Absolute paths of files that required no changes. */
    public array $skipped = [];

    /** @var array<string, string> Dry-run preview: absolute path → would-be file contents. */
    public array $preview = [];

    /** Number of translation keys added across all files this run. */
    public int $keysAdded = 0;

    public function __construct(
        public readonly string $locale,
        public readonly bool $dryRun,
    ) {}

    public function filesWritten(): int
    {
        return count($this->written);
    }

    public function filesSkipped(): int
    {
        return count($this->skipped);
    }

    public function totalFiles(): int
    {
        return $this->filesWritten() + $this->filesSkipped();
    }

    /**
     * @return array{
     *     locale: string,
     *     dryRun: bool,
     *     filesWritten: int,
     *     filesSkipped: int,
     *     keysAdded: int,
     *     written: list<string>,
     *     skipped: list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'locale' => $this->locale,
            'dryRun' => $this->dryRun,
            'filesWritten' => $this->filesWritten(),
            'filesSkipped' => $this->filesSkipped(),
            'keysAdded' => $this->keysAdded,
            'written' => $this->written,
            'skipped' => $this->skipped,
        ];
    }
}
