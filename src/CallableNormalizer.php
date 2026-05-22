<?php

declare(strict_types=1);

namespace Syriable\Localizer;

use Syriable\Localizer\Contracts\Normalizer;
use Syriable\Localizer\Data\ExtractedString;

/**
 * Adapts an arbitrary callable to the {@see Normalizer} contract.
 *
 * Used internally by {@see Localizer::normalize()} to allow registration
 * of closures alongside class-based normalizers.
 *
 * @internal
 */
final readonly class CallableNormalizer implements Normalizer
{
    /**
     * @param callable(ExtractedString): ?ExtractedString $callable
     */
    public function __construct(private mixed $callable) {}

    public function normalize(ExtractedString $string): ?ExtractedString
    {
        /** @var ?ExtractedString $result */
        $result = ($this->callable)($string);

        return $result;
    }
}
