<?php

declare(strict_types=1);

use Syriable\Localizer\CallableNormalizer;
use Syriable\Localizer\Data\ExtractedString;

describe('CallableNormalizer', function () {
    it('delegates to the wrapped callable', function () {
        $normalizer = new CallableNormalizer(fn (ExtractedString $s) => makeExtractedString(value: strtoupper($s->value)));

        $result = $normalizer->normalize(makeExtractedString(value: 'hello'));

        expect($result)->not->toBeNull()
            ->and($result->value)->toBe('HELLO');
    });

    it('propagates null returns', function () {
        $normalizer = new CallableNormalizer(fn () => null);

        expect($normalizer->normalize(makeExtractedString(value: 'x')))->toBeNull();
    });

    it('passes the original string through unchanged', function () {
        $normalizer = new CallableNormalizer(fn ($s) => $s);

        $input = makeExtractedString(value: 'unchanged');
        $output = $normalizer->normalize($input);

        expect($output)->toBe($input);
    });
});
