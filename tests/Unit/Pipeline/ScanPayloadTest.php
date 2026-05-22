<?php

declare(strict_types=1);

use Syriable\Localizer\Data\DiscoveredFile;
use Syriable\Localizer\Data\ScanRequest;
use Syriable\Localizer\Pipeline\ScanPayload;

describe('ScanPayload', function () {
    it('exposes the original request', function () {
        $request = new ScanRequest(paths: ['/a']);
        $payload = new ScanPayload($request);

        expect($payload->request)->toBe($request);
    });

    it('initializes all collections as empty', function () {
        $payload = new ScanPayload(new ScanRequest(paths: ['/a']));

        expect($payload->discoveredFiles)->toBe([])
            ->and($payload->freshFiles)->toBe([])
            ->and($payload->cachedFiles)->toBe([])
            ->and($payload->freshStrings)->toBe([])
            ->and($payload->cachedStrings)->toBe([])
            ->and($payload->strings)->toBe([])
            ->and($payload->fingerprints)->toBe([]);
    });

    it('finalises to a ScanResult', function () {
        $payload = new ScanPayload(new ScanRequest(paths: ['/a']));
        $payload->strings = [makeExtractedString(value: 'x')];
        $payload->discoveredFiles = [
            new DiscoveredFile('/a', 'a', 'php', 'php', 0),
            new DiscoveredFile('/b', 'b', 'php', 'php', 0),
        ];
        $payload->cachedFiles = [
            new DiscoveredFile('/a', 'a', 'php', 'php', 0),
        ];

        $result = $payload->toResult(durationMs: 1.5);

        expect($result->strings)->toHaveCount(1)
            ->and($result->filesScanned)->toBe(2)
            ->and($result->filesFromCache)->toBe(1)
            ->and($result->durationMs)->toBe(1.5);
    });
});
