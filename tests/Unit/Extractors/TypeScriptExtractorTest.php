<?php

declare(strict_types=1);

use Syriable\Localizer\Data\DiscoveredFile;
use Syriable\Localizer\Extractors\TypeScriptExtractor;
use Syriable\Localizer\Support\CallExtractor;
use Syriable\Localizer\Support\StringClassifier;

beforeEach(function () {
    $this->extractor = new TypeScriptExtractor(new CallExtractor, new StringClassifier);
});

function makeTsFile(string $path = '/abs/useGreeting.ts'): DiscoveredFile
{
    return new DiscoveredFile(
        absolutePath: $path,
        relativePath: basename($path),
        extension: 'ts',
        extractor: 'typescript',
        size: 100,
    );
}

describe('TypeScriptExtractor', function () {
    it('identifies itself as "typescript"', function () {
        expect($this->extractor->name())->toBe('typescript');
    });

    it('matches TS file variants', function () {
        expect($this->extractor->patterns())->toBe(['*.ts', '*.tsx', '*.mts', '*.cts']);
    });

    it('extracts strings from the fixture', function () {
        $contents = file_get_contents(__DIR__.'/../../Fixtures/ts/useGreeting.ts');
        $strings = iterator_to_array($this->extractor->extract(makeTsFile(), $contents));

        $values = array_map(static fn ($s) => $s->value, $strings);

        expect($values)->toContainStringValue('TS greeting')
            ->and($values)->toContainStringValue('buttons.save')
            ->and($values)->toContainStringValue('buttons.cancel')
            ->and($values)->toContainStringValue('typed.items');
    });

    it('tags strings with the typescript extractor name', function () {
        $strings = iterator_to_array($this->extractor->extract(
            makeTsFile(),
            "t('one')",
        ));

        expect($strings[0]->extractor)->toBe('typescript');
    });

    it('does not share the javascript name', function () {
        expect($this->extractor->name())->not->toBe('javascript');
    });
});
