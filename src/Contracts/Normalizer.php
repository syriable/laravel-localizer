<?php

declare(strict_types=1);

namespace Syriable\Localizer\Contracts;

use Syriable\Localizer\Data\ExtractedString;

/**
 * Transforms or filters an extracted string before it reaches the result.
 *
 * Normalizers run in registration order. Returning null drops the string
 * from the final result. Returning a new ExtractedString replaces it.
 * Normalizers MUST be pure functions of their input — no I/O, no shared
 * mutable state.
 */
interface Normalizer
{
    public function normalize(ExtractedString $string): ?ExtractedString;
}
