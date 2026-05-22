<?php

declare(strict_types=1);

namespace Syriable\Localizer;

use Syriable\Localizer\Data\ScanRequest;
use Syriable\Localizer\Data\ScanResult;

/**
 * Fluent builder for ad-hoc scans.
 *
 * Constructed via {@see Localizer::in()}. Each fluent method returns a
 * NEW instance — the builder is immutable, so configurations can be
 * shared and reused without aliasing bugs.
 */
final readonly class PendingScan
{
    /**
     * @param list<string>      $paths
     * @param list<string>      $exclude
     * @param list<string>|null $extractors
     */
    public function __construct(
        private Localizer $engine,
        private array $paths,
        private array $exclude = [],
        private ?array $extractors = null,
        private bool $useCache = true,
    ) {}

    /**
     * @param string|list<string> $patterns
     */
    public function exclude(string|array $patterns): self
    {
        $patterns = is_string($patterns) ? [$patterns] : $patterns;

        return new self(
            engine: $this->engine,
            paths: $this->paths,
            exclude: [...$this->exclude, ...$patterns],
            extractors: $this->extractors,
            useCache: $this->useCache,
        );
    }

    /**
     * @param string|list<string> $names
     */
    public function only(string|array $names): self
    {
        $names = is_string($names) ? [$names] : $names;

        return new self(
            engine: $this->engine,
            paths: $this->paths,
            exclude: $this->exclude,
            extractors: $names,
            useCache: $this->useCache,
        );
    }

    public function fresh(): self
    {
        return new self(
            engine: $this->engine,
            paths: $this->paths,
            exclude: $this->exclude,
            extractors: $this->extractors,
            useCache: false,
        );
    }

    public function request(): ScanRequest
    {
        return new ScanRequest(
            paths: $this->paths,
            exclude: $this->exclude,
            extractors: $this->extractors,
            useCache: $this->useCache,
        );
    }

    public function scan(): ScanResult
    {
        return $this->engine->scan($this->request());
    }
}
