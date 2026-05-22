<?php

declare(strict_types=1);

namespace Syriable\Localizer;

use Syriable\Localizer\Contracts\Normalizer;
use Syriable\Localizer\Data\ExtractedString;
use Syriable\Localizer\Data\ScanRequest;
use Syriable\Localizer\Data\ScanResult;
use Syriable\Localizer\Pipeline\ScanPipeline;
use Syriable\Localizer\Support\ExtractorRegistry;

/**
 * The package's public-facing entry point.
 *
 * Three ways to invoke a scan, in increasing order of explicitness:
 *
 *   1. `Localizer::scan()`               — uses config defaults.
 *   2. `Localizer::in(...)->scan()`      — fluent builder for ad-hoc scans.
 *   3. `Localizer::scan($request)`       — fully explicit ScanRequest.
 *
 * Normalizers are registered globally and apply to ALL scans run through
 * this instance. They are kept here, rather than on the request, because
 * they are typically registered once at boot.
 */
final class Localizer
{
    /**
     * @var list<Normalizer>
     */
    private array $normalizers = [];

    /**
     * @param array{paths: list<string>, exclude: list<string>} $defaults
     */
    public function __construct(
        private readonly ScanPipeline $pipeline,
        private readonly ExtractorRegistry $registry,
        private readonly array $defaults,
    ) {}

    /**
     * Runs a scan.
     *
     * If `$request` is null, a request is constructed from the configured
     * default paths and excludes.
     */
    public function scan(?ScanRequest $request = null): ScanResult
    {
        return $this->pipeline->run($request ?? $this->defaultRequest());
    }

    /**
     * Begins a fluent builder targeting the given paths.
     *
     * @param string|list<string> $paths
     */
    public function in(string|array $paths): PendingScan
    {
        $paths = is_string($paths) ? [$paths] : $paths;

        return new PendingScan(
            engine: $this,
            paths: $paths,
            exclude: $this->defaults['exclude'],
        );
    }

    /**
     * Registers a normalizer. Normalizers run in registration order on
     * every subsequent scan.
     *
     * Accepts either a {@see Normalizer} instance or a callable with the
     * signature `function (ExtractedString): ?ExtractedString`.
     *
     * @param Normalizer|callable(ExtractedString): ?ExtractedString $normalizer
     */
    public function normalize(Normalizer|callable $normalizer): self
    {
        $this->normalizers[] = $normalizer instanceof Normalizer
            ? $normalizer
            : new CallableNormalizer($normalizer);

        return $this;
    }

    /**
     * Clears all registered normalizers. Test/debug helper.
     */
    public function withoutNormalizers(): self
    {
        $this->normalizers = [];

        return $this;
    }

    /**
     * @return list<Normalizer>
     */
    public function normalizers(): array
    {
        return $this->normalizers;
    }

    public function extractors(): ExtractorRegistry
    {
        return $this->registry;
    }

    private function defaultRequest(): ScanRequest
    {
        return new ScanRequest(
            paths: $this->defaults['paths'],
            exclude: $this->defaults['exclude'],
        );
    }
}
