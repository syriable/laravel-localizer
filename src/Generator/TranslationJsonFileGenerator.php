<?php

declare(strict_types=1);

namespace Syriable\Localizer\Generator;

use Syriable\Localizer\Contracts\GenerationStrategy;
use Syriable\Localizer\Data\ExtractedString;

/**
 * Orchestrates the generation or update of a single JSON translation file.
 *
 * For JSON-key strings the ExtractedString `value` IS the lookup key in
 * the `lang/{locale}.json` map. The generated translation value is
 * produced by applying the active strategy to that same value.
 *
 * The sequence mirrors `TranslationFileGenerator` but operates on a flat
 * key→value map instead of a nested PHP array:
 *   1. Build a flat array from the strings + strategy.
 *   2. Read the existing JSON (empty when file is absent).
 *   3. Count how many keys would be added.
 *   4. Skip when nothing to add (and not in force mode).
 *   5. Merge new keys into existing, respecting `$force`.
 *   6. Write (or preview in dry-run).
 */
final class TranslationJsonFileGenerator
{
    public function __construct(
        private readonly TranslationMergeService $merger,
        private readonly TranslationJsonRepository $repository,
    ) {}

    /**
     * @param list<ExtractedString> $strings JsonKey strings destined for this file.
     */
    public function generate(
        string $absolutePath,
        array $strings,
        GenerationStrategy $strategy,
        bool $force,
        bool $dryRun,
    ): GeneratedFileOutcome {
        $newArray = [];
        foreach ($strings as $string) {
            $newArray[$string->value] = $strategy->generate($string->value);
        }

        $existing = $this->repository->read($absolutePath);

        $keysToAdd = $force
            ? $this->merger->countAll($newArray)
            : $this->merger->countNew($existing, $newArray);

        if ($keysToAdd === 0 && ! $force) {
            return new GeneratedFileOutcome(
                absolutePath: $absolutePath,
                written: false,
                keysAdded: 0,
                preview: null,
            );
        }

        $merged = $this->merger->merge($existing, $newArray, $force);

        if ($dryRun) {
            return new GeneratedFileOutcome(
                absolutePath: $absolutePath,
                written: true,
                keysAdded: $keysToAdd,
                preview: $this->repository->preview($merged),
            );
        }

        $this->repository->write($absolutePath, $merged);

        return new GeneratedFileOutcome(
            absolutePath: $absolutePath,
            written: true,
            keysAdded: $keysToAdd,
            preview: null,
        );
    }
}
