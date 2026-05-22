<?php

declare(strict_types=1);

namespace Syriable\Localizer\Events;

use Syriable\Localizer\Data\ScanRequest;

/**
 * Fired immediately before file discovery begins.
 *
 * Listeners can use this hook for progress bars, telemetry, or to log
 * the parameters of a scan. The request is the canonical, validated
 * version — listeners can rely on it being internally consistent.
 */
final readonly class ScanStarted
{
    public function __construct(public ScanRequest $request) {}
}
