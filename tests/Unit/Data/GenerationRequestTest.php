<?php

declare(strict_types=1);

use Syriable\Localizer\Data\GenerationRequest;
use Syriable\Localizer\Data\ScanResult;

function makeScanResult(): ScanResult
{
    return new ScanResult(strings: [], filesScanned: 0, filesFromCache: 0, durationMs: 0.0);
}

describe('GenerationRequest', function () {
    it('constructs with required fields', function () {
        $request = new GenerationRequest(
            result: makeScanResult(),
            locale: 'en',
        );

        expect($request->locale)->toBe('en')
            ->and($request->strategy)->toBe('humanized')
            ->and($request->dryRun)->toBeFalse()
            ->and($request->force)->toBeFalse()
            ->and($request->namespace)->toBeNull();
    });

    it('accepts valid locale codes', function () {
        foreach (['en', 'fr', 'fr-CA', 'zh_CN', 'en-GB', 'pt_BR'] as $locale) {
            expect(fn () => new GenerationRequest(makeScanResult(), $locale))->not->toThrow(InvalidArgumentException::class);
        }
    });

    it('rejects an empty locale', function () {
        expect(fn () => new GenerationRequest(makeScanResult(), ''))->toThrow(InvalidArgumentException::class);
    });

    it('rejects a locale with path-traversal characters', function () {
        expect(fn () => new GenerationRequest(makeScanResult(), '../etc/passwd'))->toThrow(InvalidArgumentException::class);
        expect(fn () => new GenerationRequest(makeScanResult(), 'en/foo'))->toThrow(InvalidArgumentException::class);
    });

    it('rejects an empty strategy', function () {
        expect(fn () => new GenerationRequest(makeScanResult(), 'en', strategy: ''))->toThrow(InvalidArgumentException::class);
    });

    it('stores all fields correctly', function () {
        $result = makeScanResult();
        $request = new GenerationRequest(
            result: $result,
            locale: 'fr',
            strategy: 'key',
            basePath: '/app',
            dryRun: true,
            force: true,
            namespace: 'acme',
        );

        expect($request->result)->toBe($result)
            ->and($request->locale)->toBe('fr')
            ->and($request->strategy)->toBe('key')
            ->and($request->basePath)->toBe('/app')
            ->and($request->dryRun)->toBeTrue()
            ->and($request->force)->toBeTrue()
            ->and($request->namespace)->toBe('acme');
    });
});
