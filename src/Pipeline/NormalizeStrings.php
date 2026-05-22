<?php

declare(strict_types=1);

namespace Syriable\Localizer\Pipeline;

use Closure;
use Syriable\Localizer\Contracts\Normalizer;

/**
 * Runs cached + fresh strings through the registered normalizer chain.
 *
 * Normalizers run in registration order. A normalizer that returns null
 * removes the string from the result. The final list preserves
 * extraction order, with cached strings first (they were discovered
 * first) followed by freshly-extracted strings.
 *
 * Crucially, this stage does NOT deduplicate. Deduplication is a caller
 * concern, exposed via {@see ScanResult::unique()}, because some
 * consumers want every callsite (linters checking duplicates across
 * files) and others want unique values (lang-file writers).
 */
final class NormalizeStrings
{
    /**
     * @param list<Normalizer> $normalizers
     */
    public function __construct(private readonly array $normalizers) {}

    public function handle(ScanPayload $payload, Closure $next): ScanPayload
    {
        $strings = [...$payload->cachedStrings, ...$payload->freshStrings];

        if ($this->normalizers === []) {
            $payload->strings = $strings;

            return $next($payload);
        }

        $output = [];

        foreach ($strings as $string) {
            $current = $string;

            foreach ($this->normalizers as $normalizer) {
                $current = $normalizer->normalize($current);

                if ($current === null) {
                    continue 2;
                }
            }

            $output[] = $current;
        }

        $payload->strings = $output;

        return $next($payload);
    }
}
