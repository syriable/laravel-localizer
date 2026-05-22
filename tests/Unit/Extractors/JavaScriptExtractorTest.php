<?php

declare(strict_types=1);

use Syriable\Localizer\Data\DiscoveredFile;
use Syriable\Localizer\Extractors\JavaScriptExtractor;
use Syriable\Localizer\Support\CallExtractor;
use Syriable\Localizer\Support\StringClassifier;

beforeEach(function () {
    $this->extractor = new JavaScriptExtractor(new CallExtractor, new StringClassifier);
});

function makeJsFile(string $path = '/abs/greetings.js'): DiscoveredFile
{
    return new DiscoveredFile(
        absolutePath: $path,
        relativePath: basename($path),
        extension: 'js',
        extractor: 'javascript',
        size: 100,
    );
}

describe('JavaScriptExtractor', function () {
    it('identifies itself as "javascript"', function () {
        expect($this->extractor->name())->toBe('javascript');
    });

    it('matches JS file variants', function () {
        expect($this->extractor->patterns())->toBe(['*.js', '*.jsx', '*.mjs', '*.cjs']);
    });

    it('extracts strings from the fixture', function () {
        $contents = file_get_contents(__DIR__.'/../../Fixtures/js/greetings.js');
        $strings = iterator_to_array($this->extractor->extract(makeJsFile(), $contents));

        $values = array_map(static fn ($s) => $s->value, $strings);

        expect($values)->toContainStringValue('Hello from JS')
            ->and($values)->toContainStringValue('farewell.message')
            ->and($values)->toContainStringValue('status.online_users')
            ->and($values)->toContainStringValue('errors.network')
            ->and($values)->toContainStringValue('errors.timeout');
    });

    it('does not extract dynamic calls', function () {
        $strings = iterator_to_array($this->extractor->extract(
            makeJsFile(),
            'const x = __(key)',
        ));

        expect($strings)->toBe([]);
    });

    it('tags strings with the javascript extractor name', function () {
        $strings = iterator_to_array($this->extractor->extract(
            makeJsFile(),
            "__('one')",
        ));

        expect($strings[0]->extractor)->toBe('javascript');
    });
});
