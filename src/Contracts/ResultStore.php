<?php

declare(strict_types=1);

namespace Syriable\Localizer\Contracts;

use Syriable\Localizer\Data\ScanResult;

/**
 * Persists the most recent ScanResult for cross-process access.
 *
 * This is distinct from {@see ScanCache}: the cache is per-file and
 * internal to the engine; the result store is whole-result and exists
 * solely to expose the latest scan to downstream tooling (Vite plugins,
 * dashboards, CI scripts) without re-running the scan.
 *
 * Implementations MAY be no-ops if persistence is not desired.
 */
interface ResultStore
{
    public function put(ScanResult $result): void;

    public function latest(): ?ScanResult;
}
