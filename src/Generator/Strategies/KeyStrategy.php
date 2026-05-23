<?php

declare(strict_types=1);

namespace Syriable\Localizer\Generator\Strategies;

use Syriable\Localizer\Contracts\GenerationStrategy;

/**
 * Uses the raw dot-notation key as the placeholder value.
 *
 * Useful when you want translation files to be self-documenting: the
 * key and value are identical so the UI shows exactly which key is
 * missing rather than a humanised string.
 *
 * Example: `"auth.login.failed"` → `"auth.login.failed"`.
 */
final class KeyStrategy implements GenerationStrategy
{
    public function name(): string
    {
        return 'key';
    }

    public function generate(string $key): string
    {
        return $key;
    }
}
