<?php

declare(strict_types=1);

namespace Syriable\Localizer\Contracts;

/**
 * Extends {@see GenerationStrategy} for strategies that need to know the
 * target locale before generating values.
 *
 * The `TranslationGenerationPipeline` checks whether the resolved strategy
 * implements this interface and, if so, calls `withLocale()` before it begins
 * iterating over strings. The returned instance (which may or may not be the
 * same object) is then used for all `generate()` calls within that locale run.
 */
interface LocaleAwareStrategy extends GenerationStrategy
{
    /**
     * Returns a (possibly new) instance configured for the given locale.
     *
     * Implementations that are immutable value objects should return a new
     * instance; stateful implementations may mutate and return `$this`.
     */
    public function withLocale(string $locale): static;
}
