<?php

declare(strict_types=1);

namespace Syriable\Localizer\Generator;

/**
 * The result of processing a single translation file.
 *
 * Returned by `TranslationFileGenerator::generate()` so the pipeline can
 * aggregate statistics without needing to reach into the generator's internals.
 */
final readonly class GeneratedFileOutcome
{
    /**
     * @param bool        $written   True when the file was written or would be
     *                               written (dry-run). False when skipped.
     * @param int         $keysAdded Number of translation keys added or that
     *                               would be added.
     * @param string|null $preview   The rendered PHP source code in a dry-run;
     *                               null when the file was written for real or skipped.
     */
    public function __construct(
        public string $absolutePath,
        public bool $written,
        public int $keysAdded,
        public ?string $preview,
    ) {}
}
