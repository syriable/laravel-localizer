<?php

declare(strict_types=1);

namespace Syriable\Localizer\Generator;

use Syriable\Localizer\Contracts\GenerationStrategy;
use Syriable\Localizer\Data\ExtractedString;

/**
 * Orchestrates the generation or update of a single translation PHP file.
 *
 * The sequence for each file:
 *   1. Build a new array from the strings + strategy.
 *   2. Read the existing array (empty when file is absent).
 *   3. Count how many keys would be added.
 *   4. Skip when nothing to add (and not in force mode).
 *   5. Merge new keys into existing, respecting `$force`.
 *   6. Write (or preview in dry-run).
 *
 * Returns a `GeneratedFileOutcome` describing what happened.
 */
final class TranslationFileGenerator
{
    public function __construct(
        private readonly TranslationArrayBuilder $builder,
        private readonly TranslationMergeService $merger,
        private readonly TranslationFileRepository $repository,
    ) {}

    /**
     * @param list<ExtractedString> $strings Strings destined for this file.
     */
    public function generate(
        string $absolutePath,
        array $strings,
        GenerationStrategy $strategy,
        bool $force,
        bool $dryRun,
    ): GeneratedFileOutcome {
        $newArray = $this->builder->build($strings, $strategy);
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
