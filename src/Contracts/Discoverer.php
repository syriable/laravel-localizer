<?php

declare(strict_types=1);

namespace Syriable\Localizer\Contracts;

use Syriable\Localizer\Data\DiscoveredFile;
use Syriable\Localizer\Data\ScanRequest;

/**
 * Walks the filesystem and produces a stream of files to extract from.
 *
 * Implementations are responsible for path resolution, recursion,
 * exclusion-pattern matching, and extractor resolution. They MUST yield
 * only files whose extractor is registered; files with no matching
 * extractor are silently skipped.
 */
interface Discoverer
{
    /**
     * @return iterable<DiscoveredFile>
     */
    public function discover(ScanRequest $request): iterable;
}
