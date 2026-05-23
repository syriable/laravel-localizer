<?php

declare(strict_types=1);

namespace Syriable\Localizer\Generator;

/**
 * Merges a newly-generated translation array into an existing one.
 *
 * By default (without `$force`) only keys that are absent from `$existing`
 * are added. When `$force` is true every key in `$new` is written,
 * overwriting any existing values. In both modes the merge is recursive:
 * sub-arrays are merged key-by-key rather than replaced wholesale.
 */
final class TranslationMergeService
{
    /**
     * Returns the count of keys that would be added when merging `$new` into `$existing`.
     *
     * This is a pure query — no mutation occurs.
     *
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $new
     */
    public function countNew(array $existing, array $new): int
    {
        $count = 0;

        foreach ($new as $key => $value) {
            if (! isset($existing[$key])) {
                $count += is_array($value) ? $this->countAll($value) : 1;
            } elseif (is_array($value) && is_array($existing[$key])) {
                /** @var array<string, mixed> $existingChild */
                $existingChild = $existing[$key];
                /** @var array<string, mixed> $value */
                $count += $this->countNew($existingChild, $value);
            }
        }

        return $count;
    }

    /**
     * Merges `$new` into `$existing`, returning the combined array.
     *
     * @param  array<string, mixed> $existing
     * @param  array<string, mixed> $new
     * @return array<string, mixed>
     */
    public function merge(array $existing, array $new, bool $force = false): array
    {
        foreach ($new as $key => $value) {
            if (! isset($existing[$key])) {
                $existing[$key] = $value;
            } elseif (is_array($value) && is_array($existing[$key])) {
                /** @var array<string, mixed> $existingChild */
                $existingChild = $existing[$key];
                /** @var array<string, mixed> $value */
                $existing[$key] = $this->merge($existingChild, $value, $force);
            } elseif ($force) {
                $existing[$key] = $value;
            }
            // Without force: key exists, not an array — preserve existing value.
        }

        return $existing;
    }

    /**
     * Counts all leaf values in a nested array.
     *
     * @param array<string, mixed> $array
     */
    public function countAll(array $array): int
    {
        $count = 0;

        foreach ($array as $value) {
            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $count += $this->countAll($value);
            } else {
                $count++;
            }
        }

        return $count;
    }
}
