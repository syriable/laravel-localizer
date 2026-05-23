<?php

declare(strict_types=1);

namespace Syriable\Localizer\Generator\Strategies;

use Syriable\Localizer\Contracts\GenerationStrategy;

/**
 * Always generates an empty string as the placeholder value.
 *
 * Useful when you want to pre-generate the file structure and fill in
 * translations manually afterwards without any pre-populated placeholders.
 */
final class EmptyStrategy implements GenerationStrategy
{
    public function name(): string
    {
        return 'empty';
    }

    public function generate(string $key): string
    {
        return '';
    }
}
