<?php

declare(strict_types=1);

namespace Syriable\Localizer\Events;

use Syriable\Localizer\Data\DiscoveredFile;
use Syriable\Localizer\Data\ExtractedString;
use Syriable\Localizer\Data\ScanResult;

/**
 * Fired once per file processed during a scan.
 *
 * `fromCache` distinguishes cache hits from fresh extractions. The
 * strings list is the post-extractor, pre-normalizer set — listeners
 * observing this event should not assume the strings will appear
 * verbatim in the final {@see ScanResult}.
 */
final readonly class FileExtracted
{
    /**
     * @param list<ExtractedString> $strings
     */
    public function __construct(
        public DiscoveredFile $file,
        public array $strings,
        public bool $fromCache,
    ) {}
}
