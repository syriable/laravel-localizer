<?php

declare(strict_types=1);

namespace Syriable\Localizer\Support;

use Syriable\Localizer\Contracts\Extractor;
use Syriable\Localizer\Exceptions\UnknownExtractorException;

/**
 * The single source of truth for which extractors exist and how to find one
 * for a given file.
 *
 * Registration order is preserved. When multiple extractors match the
 * same file (e.g. `*.blade.php` matches both BladeExtractor and
 * PhpExtractor), the FIRST registered match wins — this is why
 * BladeExtractor must be registered before PhpExtractor.
 */
final class ExtractorRegistry
{
    /** @var array<string, Extractor> */
    private array $extractors = [];

    public function register(Extractor $extractor): void
    {
        $this->extractors[$extractor->name()] = $extractor;
    }

    public function has(string $name): bool
    {
        return isset($this->extractors[$name]);
    }

    public function get(string $name): Extractor
    {
        if (! isset($this->extractors[$name])) {
            throw UnknownExtractorException::forName($name);
        }

        return $this->extractors[$name];
    }

    /**
     * Resolves the extractor responsible for the given file basename,
     * or null if none matches.
     */
    public function resolveForBasename(string $basename): ?Extractor
    {
        foreach ($this->extractors as $extractor) {
            foreach ($extractor->patterns() as $pattern) {
                if (fnmatch($pattern, $basename, FNM_CASEFOLD)) {
                    return $extractor;
                }
            }
        }

        return null;
    }

    /**
     * Returns all registered extractors, in registration order.
     *
     * @return array<string, Extractor>
     */
    public function all(): array
    {
        return $this->extractors;
    }

    /**
     * Returns a copy of the registry restricted to the given names.
     *
     * @param list<string> $names
     */
    public function only(array $names): self
    {
        $copy = new self;

        foreach ($names as $name) {
            $copy->register($this->get($name));
        }

        return $copy;
    }

    public function count(): int
    {
        return count($this->extractors);
    }
}
