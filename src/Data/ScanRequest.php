<?php

declare(strict_types=1);

namespace Syriable\Localizer\Data;

/**
 * The input to a single scan invocation.
 *
 * Instances are immutable; mutate via {@see with()} helpers if needed.
 * Path strings are accepted as-is and resolved to absolute paths during
 * discovery. Non-existing paths are ignored without raising.
 */
final readonly class ScanRequest
{
    /**
     * @param list<string>      $paths      Absolute or base-relative paths to scan.
     * @param list<string>      $exclude    Glob-style exclusion patterns.
     * @param list<string>|null $extractors Restrict to these extractor names, or null for all.
     * @param bool              $useCache   When false, ignore and overwrite the cache.
     */
    public function __construct(
        public array $paths,
        public array $exclude = [],
        public ?array $extractors = null,
        public bool $useCache = true,
    ) {
        if ($paths === []) {
            throw new \InvalidArgumentException('ScanRequest must include at least one path.');
        }

        self::assertNonEmptyStringList($paths, 'paths');

        if ($extractors !== null) {
            if ($extractors === []) {
                throw new \InvalidArgumentException(
                    'ScanRequest extractors must be null or a non-empty list.',
                );
            }

            self::assertNonEmptyStringList($extractors, 'extractor names');
        }
    }

    /**
     * @param array<int, mixed> $values
     */
    private static function assertNonEmptyStringList(array $values, string $label): void
    {
        foreach ($values as $value) {
            if (! is_string($value) || $value === '') {
                throw new \InvalidArgumentException(
                    "ScanRequest {$label} must be non-empty strings.",
                );
            }
        }
    }

    /**
     * Returns a clone with caching disabled (forces a fresh scan).
     */
    public function fresh(): self
    {
        return new self($this->paths, $this->exclude, $this->extractors, useCache: false);
    }

    /**
     * Returns a clone restricted to the given extractor names.
     *
     * @param list<string> $extractors
     */
    public function only(array $extractors): self
    {
        return new self($this->paths, $this->exclude, $extractors, $this->useCache);
    }
}
