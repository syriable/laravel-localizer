<?php

declare(strict_types=1);

namespace Syriable\Localizer\Contracts;

/**
 * A pluggable strategy for generating placeholder translation values.
 *
 * Implementations receive the dot-notation key within the translation file
 * (e.g. `"submit.label"`, `"auth.login.failed"`) and return a string that
 * will be used as the placeholder value when a key is missing.
 *
 * Built-in strategies:
 *   - `humanized` — converts the last key segment to a readable phrase
 *   - `key`       — uses the raw dot-notation key as-is
 *   - `empty`     — always returns an empty string
 */
interface GenerationStrategy
{
    /**
     * Returns a human-readable name for this strategy.
     *
     * Used in config and Artisan command options.
     */
    public function name(): string;

    /**
     * Generates a placeholder value for the given dot-notation key.
     *
     * @param string $key The dot-notation key within the translation file (e.g. `"submit.label"`).
     */
    public function generate(string $key): string;
}
