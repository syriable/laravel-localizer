<?php

declare(strict_types=1);

namespace Syriable\Localizer\Data;

use Syriable\Localizer\Analysis\TranslationCallAnalysis;
use Syriable\Localizer\Contracts\AnalysisAwareStrategy;

/**
 * Parameters for a single-locale translation file generation run.
 *
 * The `result` field carries the scan output. The generator iterates every
 * ShortKey string in the result, groups them by target file, and writes
 * (or previews) the missing translation keys.
 */
final readonly class GenerationRequest
{
    /**
     * @param ScanResult                             $result       The scan result supplying the translatable strings.
     * @param string                                 $locale       BCP-47 locale code (e.g. `"en"`, `"fr-CA"`). Validated
     *                                                             against `[A-Za-z0-9_-]+`.
     * @param string                                 $strategy     Strategy name: `"humanized"`, `"key"`, or `"empty"`.
     * @param string                                 $basePath     Absolute path to the application root (used to construct
     *                                                             the full path to each `lang/` file).
     * @param bool                                   $dryRun       When true the generator computes the output but never
     *                                                             touches the filesystem.
     * @param bool                                   $force        When true existing translation values are overwritten
     *                                                             with the generated placeholder. Without this flag only
     *                                                             missing keys are added.
     * @param string|null                            $namespace    When set, restricts generation to strings whose
     *                                                             `package` field matches this value. Pass `null` to
     *                                                             generate for all strings regardless of package.
     * @param array<string, TranslationCallAnalysis> $callAnalyses Call-site analyses
     *                                                             keyed by translation key. Passed to strategies that
     *                                                             implement {@see AnalysisAwareStrategy}
     *                                                             so they can enrich generated values with placeholder context.
     */
    public function __construct(
        public ScanResult $result,
        public string $locale,
        public string $strategy = 'humanized',
        public string $basePath = '',
        public bool $dryRun = false,
        public bool $force = false,
        public ?string $namespace = null,
        public array $callAnalyses = [],
    ) {
        if ($locale === '' || preg_match('/^[A-Za-z0-9_-]+$/', $locale) !== 1) {
            throw new \InvalidArgumentException(
                "Invalid locale [{$locale}] — expected letters, digits, `_` or `-` only.",
            );
        }

        if ($strategy === '') {
            throw new \InvalidArgumentException('GenerationRequest strategy cannot be empty.');
        }
    }
}
