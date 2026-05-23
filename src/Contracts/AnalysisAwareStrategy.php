<?php

declare(strict_types=1);

namespace Syriable\Localizer\Contracts;

use Syriable\Localizer\Analysis\TranslationCallAnalysis;

/**
 * Extends {@see GenerationStrategy} for strategies that can make use of
 * static call-site analysis when generating translation values.
 *
 * The `TranslationGenerationPipeline` checks whether the resolved strategy
 * implements this interface and, if analysis data is available, calls
 * `withAnalysis()` so the strategy can enrich its output — for example, by
 * including placeholder descriptions in an AI prompt.
 */
interface AnalysisAwareStrategy extends GenerationStrategy
{
    /**
     * Returns a (possibly new) instance pre-loaded with call-site analyses.
     *
     * The map is keyed by the translation key (e.g. `"auth.login.failed"` or
     * `"Welcome back, :name!"`). When multiple call sites use the same key,
     * the first analysis found takes precedence.
     *
     * @param array<string, TranslationCallAnalysis> $analyses
     */
    public function withAnalysis(array $analyses): static;
}
