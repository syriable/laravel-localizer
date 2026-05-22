<?php

declare(strict_types=1);

use Syriable\Localizer\Contracts\Normalizer;
use Syriable\Localizer\Data\ExtractedString;
use Syriable\Localizer\Data\ScanRequest;
use Syriable\Localizer\Pipeline\NormalizeStrings;
use Syriable\Localizer\Pipeline\ScanPayload;

function passthroughNext(): Closure
{
    return static fn (ScanPayload $p): ScanPayload => $p;
}

describe('NormalizeStrings', function () {
    it('passes strings through unchanged when no normalizers are registered', function () {
        $stage = new NormalizeStrings([]);

        $payload = new ScanPayload(new ScanRequest(paths: ['/a']));
        $payload->freshStrings = [makeExtractedString(value: 'one'), makeExtractedString(value: 'two')];
        $payload->cachedStrings = [makeExtractedString(value: 'cached')];

        $stage->handle($payload, passthroughNext());

        expect($payload->strings)->toHaveCount(3)
            ->and($payload->strings[0]->value)->toBe('cached')
            ->and($payload->strings[1]->value)->toBe('one')
            ->and($payload->strings[2]->value)->toBe('two');
    });

    it('applies normalizers in registration order', function () {
        $upper = new class implements Normalizer
        {
            public function normalize(ExtractedString $string): ?ExtractedString
            {
                return makeExtractedString(value: strtoupper($string->value));
            }
        };

        $exclaim = new class implements Normalizer
        {
            public function normalize(ExtractedString $string): ?ExtractedString
            {
                return makeExtractedString(value: $string->value.'!');
            }
        };

        $stage = new NormalizeStrings([$upper, $exclaim]);

        $payload = new ScanPayload(new ScanRequest(paths: ['/a']));
        $payload->freshStrings = [makeExtractedString(value: 'hello')];

        $stage->handle($payload, passthroughNext());

        expect($payload->strings[0]->value)->toBe('HELLO!');
    });

    it('drops strings when a normalizer returns null', function () {
        $drop = new class implements Normalizer
        {
            public function normalize(ExtractedString $string): ?ExtractedString
            {
                return $string->value === 'drop me' ? null : $string;
            }
        };

        $stage = new NormalizeStrings([$drop]);

        $payload = new ScanPayload(new ScanRequest(paths: ['/a']));
        $payload->freshStrings = [
            makeExtractedString(value: 'keep me'),
            makeExtractedString(value: 'drop me'),
            makeExtractedString(value: 'also keep'),
        ];

        $stage->handle($payload, passthroughNext());

        expect($payload->strings)->toHaveCount(2)
            ->and($payload->strings[0]->value)->toBe('keep me')
            ->and($payload->strings[1]->value)->toBe('also keep');
    });

    it('does not deduplicate strings', function () {
        $stage = new NormalizeStrings([]);

        $payload = new ScanPayload(new ScanRequest(paths: ['/a']));
        $payload->freshStrings = [
            makeExtractedString(value: 'duplicate'),
            makeExtractedString(value: 'duplicate'),
        ];

        $stage->handle($payload, passthroughNext());

        expect($payload->strings)->toHaveCount(2);
    });

    it('short-circuits remaining normalizers when an earlier one returns null', function () {
        $counter = new stdClass;
        $counter->calls = 0;

        $drop = new class implements Normalizer
        {
            public function normalize(ExtractedString $string): ?ExtractedString
            {
                return null;
            }
        };

        $shouldNotRun = new class($counter) implements Normalizer
        {
            public function __construct(private readonly stdClass $counter) {}

            public function normalize(ExtractedString $string): ?ExtractedString
            {
                $this->counter->calls++;

                return $string;
            }
        };

        $stage = new NormalizeStrings([$drop, $shouldNotRun]);

        $payload = new ScanPayload(new ScanRequest(paths: ['/a']));
        $payload->freshStrings = [makeExtractedString(value: 'x')];

        $stage->handle($payload, passthroughNext());

        expect($counter->calls)->toBe(0)
            ->and($payload->strings)->toBe([]);
    });

    it('calls the next stage in the pipeline', function () {
        $stage = new NormalizeStrings([]);
        $called = false;

        $next = function (ScanPayload $p) use (&$called): ScanPayload {
            $called = true;

            return $p;
        };

        $payload = new ScanPayload(new ScanRequest(paths: ['/a']));
        $stage->handle($payload, $next);

        expect($called)->toBeTrue();
    });
});
