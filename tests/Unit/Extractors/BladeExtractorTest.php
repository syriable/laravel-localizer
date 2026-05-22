<?php

declare(strict_types=1);

use Syriable\Localizer\Data\DiscoveredFile;
use Syriable\Localizer\Data\StringKind;
use Syriable\Localizer\Extractors\BladeExtractor;
use Syriable\Localizer\Support\CallExtractor;
use Syriable\Localizer\Support\StringClassifier;

beforeEach(function () {
    $this->extractor = new BladeExtractor(new CallExtractor, new StringClassifier);
});

function makeBladeFile(string $path = '/abs/welcome.blade.php'): DiscoveredFile
{
    return new DiscoveredFile(
        absolutePath: $path,
        relativePath: basename($path),
        extension: 'php',
        extractor: 'blade',
        size: 100,
    );
}

describe('BladeExtractor', function () {
    it('identifies itself as "blade"', function () {
        expect($this->extractor->name())->toBe('blade');
    });

    it('matches *.blade.php files', function () {
        expect($this->extractor->patterns())->toBe(['*.blade.php']);
    });

    it('extracts strings from a fixture file', function () {
        $contents = file_get_contents(__DIR__.'/../../Fixtures/blade/sample.blade.php');
        $strings = iterator_to_array($this->extractor->extract(makeBladeFile(), $contents));

        $values = array_map(static fn ($s) => $s->value, $strings);

        expect($values)->toContainStringValue('Welcome back')
            ->and($values)->toContainStringValue('app.title')
            ->and($values)->toContainStringValue('pagination.next')
            ->and($values)->toContainStringValue('messages.apples')
            ->and($values)->toContainStringValue('Hello, world')
            ->and($values)->toContainStringValue('validation.required')
            ->and($values)->toContainStringValue('Inline PHP block')
            ->and($values)->toContainStringValue('auth.failed');
    });

    it('extracts escaped quotes correctly', function () {
        $contents = file_get_contents(__DIR__.'/../../Fixtures/blade/sample.blade.php');
        $strings = iterator_to_array($this->extractor->extract(makeBladeFile(), $contents));

        $values = array_map(static fn ($s) => $s->value, $strings);

        expect($values)->toContainStringValue("Escaped: it's working");
    });

    it('extracts double-quoted strings', function () {
        $contents = file_get_contents(__DIR__.'/../../Fixtures/blade/sample.blade.php');
        $strings = iterator_to_array($this->extractor->extract(makeBladeFile(), $contents));

        $values = array_map(static fn ($s) => $s->value, $strings);

        expect($values)->toContainStringValue('Double-quoted text');
    });

    it('ignores dynamic-key callsites', function () {
        $strings = iterator_to_array($this->extractor->extract(
            makeBladeFile(),
            '{{ __($someVariable) }}',
        ));

        expect($strings)->toBe([]);
    });

    it('classifies dotted keys as ShortKey with file/key decomposition', function () {
        $strings = iterator_to_array($this->extractor->extract(
            makeBladeFile(),
            "@lang('pagination.next')",
        ));

        expect($strings)->toHaveCount(1)
            ->and($strings[0]->kind)->toBe(StringKind::ShortKey)
            ->and($strings[0]->file)->toBe('pagination')
            ->and($strings[0]->key)->toBe('next');
    });

    it('classifies free text as JsonKey with null decomposition', function () {
        $strings = iterator_to_array($this->extractor->extract(
            makeBladeFile(),
            "{{ __('Hello, world') }}",
        ));

        expect($strings[0]->kind)->toBe(StringKind::JsonKey)
            ->and($strings[0]->file)->toBeNull()
            ->and($strings[0]->key)->toBeNull();
    });

    it('tags every extracted string with the blade extractor name', function () {
        $strings = iterator_to_array($this->extractor->extract(
            makeBladeFile(),
            "@lang('a') {{ __('b') }} {{ trans('c.d') }}",
        ));

        foreach ($strings as $s) {
            expect($s->extractor)->toBe('blade');
        }
    });

    it('records the line of each extraction', function () {
        $contents = "line1\nline2 @lang('hit')\nline3";
        $strings = iterator_to_array($this->extractor->extract(makeBladeFile(), $contents));

        expect($strings[0]->location->line)->toBe(2);
    });

    it('records the file path on every extraction', function () {
        $strings = iterator_to_array($this->extractor->extract(
            makeBladeFile('/views/abs.blade.php'),
            "@lang('x')",
        ));

        expect($strings[0]->location->path)->toBe('/views/abs.blade.php');
    });

    it('returns nothing for an empty file', function () {
        $strings = iterator_to_array($this->extractor->extract(makeBladeFile(), ''));

        expect($strings)->toBe([]);
    });

    it('returns nothing when no translation calls are present', function () {
        $strings = iterator_to_array($this->extractor->extract(
            makeBladeFile(),
            '<html><body>plain content with no helpers</body></html>',
        ));

        expect($strings)->toBe([]);
    });
});
