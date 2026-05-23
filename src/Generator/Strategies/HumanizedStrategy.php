<?php

declare(strict_types=1);

namespace Syriable\Localizer\Generator\Strategies;

use Syriable\Localizer\Analysis\PlaceholderAnalysis;
use Syriable\Localizer\Analysis\TranslationCallAnalysis;
use Syriable\Localizer\Contracts\AnalysisAwareStrategy;

/**
 * Generates a human-readable placeholder from the translation key, optionally
 * appending Laravel-style `:placeholder` tokens when call-site analysis data
 * is available.
 *
 * Takes the last segment of the dot-notation key, replaces underscores and
 * hyphens with spaces, then applies `ucfirst`. Examples (no analysis):
 *
 *   `"submit"`               → `"Submit"`
 *   `"submit.label"`         → `"Label"`
 *   `"auth.login.failed"`    → `"Failed"`
 *   `"btn.submit_form"`      → `"Submit form"`
 *
 * When analysis reports placeholders for the key:
 *
 *   `"actions.send"` with `['label' => $x]` → `"Send :label"`
 */
final class HumanizedStrategy implements AnalysisAwareStrategy
{
    /**
     * @param array<string, TranslationCallAnalysis> $analyses
     */
    public function __construct(
        private readonly array $analyses = [],
    ) {}

    public function name(): string
    {
        return 'humanized';
    }

    /**
     * @param array<string, TranslationCallAnalysis> $analyses
     */
    public function withAnalysis(array $analyses): static
    {
        return new self(analyses: $analyses);
    }

    public function generate(string $key): string
    {
        if ($key === '') {
            return '';
        }

        $segments = explode('.', $key);
        $last = $segments[count($segments) - 1];
        $base = ucfirst(str_replace(['_', '-'], ' ', $last));

        if (isset($this->analyses[$key]) && $this->analyses[$key]->placeholders !== []) {
            $tokens = array_map(
                static fn (PlaceholderAnalysis $p): string => $p->placeholder,
                $this->analyses[$key]->placeholders,
            );

            return $base.' '.implode(' ', $tokens);
        }

        return $base;
    }
}
