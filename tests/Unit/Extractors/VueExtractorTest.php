<?php

declare(strict_types=1);

use Syriable\Localizer\Data\DiscoveredFile;
use Syriable\Localizer\Extractors\VueExtractor;
use Syriable\Localizer\Support\CallExtractor;
use Syriable\Localizer\Support\StringClassifier;

beforeEach(function () {
    $this->extractor = new VueExtractor(new CallExtractor, new StringClassifier);
});

function makeVueFile(string $path = '/abs/App.vue'): DiscoveredFile
{
    return new DiscoveredFile(
        absolutePath: $path,
        relativePath: basename($path),
        extension: 'vue',
        extractor: 'vue',
        size: 100,
    );
}

describe('VueExtractor', function () {
    it('identifies itself as "vue"', function () {
        expect($this->extractor->name())->toBe('vue');
    });

    it('matches *.vue files', function () {
        expect($this->extractor->patterns())->toBe(['*.vue']);
    });

    it('extracts strings across template and script blocks', function () {
        $contents = file_get_contents(__DIR__.'/../../Fixtures/vue/Dashboard.vue');
        $strings = iterator_to_array($this->extractor->extract(makeVueFile(), $contents));

        $values = array_map(static fn ($s) => $s->value, $strings);

        expect($values)->toContainStringValue('Dashboard heading')
            ->and($values)->toContainStringValue('messages.items')
            ->and($values)->toContainStringValue('buttons.save')
            ->and($values)->toContainStringValue('Hello there')
            ->and($values)->toContainStringValue('dashboard.subtitle');
    });

    it('extracts $t, $tc, t, and tc helpers', function () {
        $strings = iterator_to_array($this->extractor->extract(
            makeVueFile(),
            "\$t('a') \$tc('b') t('c') tc('d')",
        ));

        $values = array_map(static fn ($s) => $s->value, $strings);

        expect($values)->toContain('a', 'b', 'c', 'd');
    });

    it('ignores dynamic calls', function () {
        $contents = file_get_contents(__DIR__.'/../../Fixtures/vue/Dashboard.vue');
        $strings = iterator_to_array($this->extractor->extract(makeVueFile(), $contents));

        $values = array_map(static fn ($s) => $s->value, $strings);

        expect($values)->not->toContain('someVariable');
    });
});
