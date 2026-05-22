<?php

declare(strict_types=1);

use Syriable\Localizer\Data\ScanRequest;

describe('ScanRequest', function () {
    it('constructs with paths only', function () {
        $request = new ScanRequest(paths: ['/a', '/b']);

        expect($request->paths)->toBe(['/a', '/b'])
            ->and($request->exclude)->toBe([])
            ->and($request->extractors)->toBeNull()
            ->and($request->useCache)->toBeTrue();
    });

    it('rejects empty paths', function () {
        new ScanRequest(paths: []);
    })->throws(InvalidArgumentException::class, 'at least one path');

    it('rejects non-string paths', function () {
        /** @phpstan-ignore-next-line */
        new ScanRequest(paths: ['/a', 42]);
    })->throws(InvalidArgumentException::class, 'non-empty strings');

    it('rejects empty string in paths', function () {
        new ScanRequest(paths: ['/a', '']);
    })->throws(InvalidArgumentException::class, 'non-empty strings');

    it('rejects empty extractors list when present', function () {
        new ScanRequest(paths: ['/a'], extractors: []);
    })->throws(InvalidArgumentException::class, 'null or a non-empty list');

    it('rejects invalid extractor names', function () {
        new ScanRequest(paths: ['/a'], extractors: ['valid', '']);
    })->throws(InvalidArgumentException::class, 'non-empty strings');

    it('fresh() disables caching', function () {
        $request = new ScanRequest(paths: ['/a']);

        expect($request->useCache)->toBeTrue()
            ->and($request->fresh()->useCache)->toBeFalse();
    });

    it('fresh() preserves other state', function () {
        $request = new ScanRequest(
            paths: ['/a'],
            exclude: ['vendor'],
            extractors: ['blade'],
        );

        $fresh = $request->fresh();

        expect($fresh->paths)->toBe($request->paths)
            ->and($fresh->exclude)->toBe($request->exclude)
            ->and($fresh->extractors)->toBe($request->extractors);
    });

    it('only() restricts extractors', function () {
        $request = new ScanRequest(paths: ['/a']);
        $restricted = $request->only(['blade', 'vue']);

        expect($restricted->extractors)->toBe(['blade', 'vue']);
    });

    it('is immutable', function () {
        $request = new ScanRequest(paths: ['/a']);
        $ref = new ReflectionClass($request);

        expect($ref->isReadOnly())->toBeTrue();
    });
});
