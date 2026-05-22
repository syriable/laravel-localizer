<?php

declare(strict_types=1);

use Syriable\Localizer\Data\DiscoveredFile;
use Syriable\Localizer\Data\ScanRequest;
use Syriable\Localizer\Data\ScanResult;
use Syriable\Localizer\Events\FileExtracted;
use Syriable\Localizer\Events\ScanCompleted;
use Syriable\Localizer\Events\ScanStarted;

describe('ScanStarted event', function () {
    it('carries the request', function () {
        $request = new ScanRequest(paths: ['/a']);
        $event = new ScanStarted($request);

        expect($event->request)->toBe($request);
    });

    it('is immutable', function () {
        $request = new ScanRequest(paths: ['/a']);
        $event = new ScanStarted($request);

        expect((new ReflectionClass($event))->isReadOnly())->toBeTrue();
    });
});

describe('FileExtracted event', function () {
    it('carries the file, strings, and cache flag', function () {
        $file = new DiscoveredFile('/abs', 'rel', 'php', 'blade', 100);
        $strings = [makeExtractedString(value: 'one')];

        $event = new FileExtracted($file, $strings, fromCache: true);

        expect($event->file)->toBe($file)
            ->and($event->strings)->toBe($strings)
            ->and($event->fromCache)->toBeTrue();
    });

    it('is immutable', function () {
        $event = new FileExtracted(
            new DiscoveredFile('/a', 'a', 'php', 'php', 0),
            [],
            fromCache: false,
        );

        expect((new ReflectionClass($event))->isReadOnly())->toBeTrue();
    });
});

describe('ScanCompleted event', function () {
    it('carries the final result', function () {
        $result = new ScanResult([], 0, 0, 0.0);
        $event = new ScanCompleted($result);

        expect($event->result)->toBe($result);
    });

    it('is immutable', function () {
        $event = new ScanCompleted(new ScanResult([], 0, 0, 0.0));

        expect((new ReflectionClass($event))->isReadOnly())->toBeTrue();
    });
});
