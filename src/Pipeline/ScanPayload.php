<?php

declare(strict_types=1);

namespace Syriable\Localizer\Pipeline;

use Syriable\Localizer\Data\DiscoveredFile;
use Syriable\Localizer\Data\ExtractedString;
use Syriable\Localizer\Data\ScanRequest;
use Syriable\Localizer\Data\ScanResult;

/**
 * Internal mutable state passed between pipeline stages.
 *
 * This is the ONLY mutable object in the engine. It never leaves the
 * pipeline — by the time the pipeline returns, the payload is
 * transformed into an immutable {@see ScanResult} and discarded. Stages
 * mutate its public properties directly because using setters here
 * would add noise without any safety benefit (the payload is fully
 * encapsulated within the pipeline).
 */
final class ScanPayload
{
    /**
     * @var list<DiscoveredFile>
     */
    public array $discoveredFiles = [];

    /**
     * Files that need re-extraction this run.
     *
     * @var list<DiscoveredFile>
     */
    public array $freshFiles = [];

    /**
     * Files whose cached extraction is still valid.
     *
     * @var list<DiscoveredFile>
     */
    public array $cachedFiles = [];

    /**
     * Newly-extracted strings, before normalization.
     *
     * @var list<ExtractedString>
     */
    public array $freshStrings = [];

    /**
     * Strings loaded from cache.
     *
     * @var list<ExtractedString>
     */
    public array $cachedStrings = [];

    /**
     * Final strings after normalization, in extraction order.
     *
     * @var list<ExtractedString>
     */
    public array $strings = [];

    /**
     * Per-file fingerprints computed during cache filtering.
     *
     * @var array<string, string>
     */
    public array $fingerprints = [];

    /**
     * Extensions encountered during discovery that had no matching extractor.
     *
     * Stored as a set (extension → count) so callers can see both which
     * extensions were unhandled AND how many files were affected. Used
     * by {@see toResult()} to populate ScanResult::$skippedExtensions.
     *
     * @var array<string, int>
     */
    public array $skippedExtensions = [];

    public function __construct(public readonly ScanRequest $request) {}

    /**
     * Finalises the payload into an immutable result.
     */
    public function toResult(float $durationMs): ScanResult
    {
        return new ScanResult(
            strings: $this->strings,
            filesScanned: count($this->discoveredFiles),
            filesFromCache: count($this->cachedFiles),
            durationMs: $durationMs,
            skippedExtensions: $this->skippedExtensions,
        );
    }
}
