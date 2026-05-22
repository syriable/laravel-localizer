<?php

declare(strict_types=1);

namespace Syriable\Localizer\Pipeline;

use Syriable\Localizer\Contracts\Discoverer;
use Syriable\Localizer\Data\ScanRequest;

/**
 * Adapts the {@see DiscoverFiles} pipeline stage to the public
 * {@see Discoverer} contract.
 *
 * This exists so downstream packages that only want file discovery
 * (no extraction, no caching, no normalization) can resolve a
 * Discoverer from the container without having to construct a
 * pipeline. It runs the stage once with a no-op next-callback.
 */
final class PipelineDiscoverer implements Discoverer
{
    public function __construct(private readonly DiscoverFiles $stage) {}

    public function discover(ScanRequest $request): iterable
    {
        $payload = new ScanPayload($request);

        $this->stage->handle($payload, static fn (ScanPayload $p): ScanPayload => $p);

        return $payload->discoveredFiles;
    }
}
