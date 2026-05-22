<?php

declare(strict_types=1);

namespace Syriable\Localizer\Events;

use Syriable\Localizer\Data\ScanResult;

/**
 * Fired after a scan completes successfully.
 *
 * This is the canonical extension point for downstream packages
 * (lang-file writers, AI translators, linters) — they listen here and
 * receive the final result with no further coordination required.
 */
final readonly class ScanCompleted
{
    public function __construct(public ScanResult $result) {}
}
