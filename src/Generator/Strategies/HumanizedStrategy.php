<?php

declare(strict_types=1);

namespace Syriable\Localizer\Generator\Strategies;

use Syriable\Localizer\Contracts\GenerationStrategy;

/**
 * Generates a human-readable placeholder from the translation key.
 *
 * Takes the last segment of the dot-notation key, replaces underscores and
 * hyphens with spaces, then applies `ucwords`. Examples:
 *
 *   `"submit"`               → `"Submit"`
 *   `"submit.label"`         → `"Submit label"`
 *   `"auth.login.failed"`    → `"Login failed"`
 *   `"btn.submit_form"`      → `"Submit form"`
 */
final class HumanizedStrategy implements GenerationStrategy
{
    public function name(): string
    {
        return 'humanized';
    }

    public function generate(string $key): string
    {
        if ($key === '') {
            return '';
        }

        $segments = explode('.', $key);
        $last = $segments[count($segments) - 1];

        return ucfirst(str_replace(['_', '-'], ' ', $last));
    }
}
