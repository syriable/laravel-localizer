<?php

declare(strict_types=1);

use Syriable\Localizer\Data\DiscoveredFile;
use Syriable\Localizer\Extractors\InertiaExtractor;
use Syriable\Localizer\Support\CallExtractor;
use Syriable\Localizer\Support\StringClassifier;

beforeEach(function () {
    $this->extractor = new InertiaExtractor(new CallExtractor, new StringClassifier);
});

function makeInertiaFile(string $path = '/abs/DashboardPage.vue'): DiscoveredFile
{
    return new DiscoveredFile(
        absolutePath: $path,
        relativePath: basename($path),
        extension: 'vue',
        extractor: 'inertia',
        size: 100,
    );
}

describe('InertiaExtractor', function () {
    it('identifies itself as "inertia"', function () {
        expect($this->extractor->name())->toBe('inertia');
    });

    it('matches Page and Layout files across Vue/TSX/JSX', function () {
        expect($this->extractor->patterns())->toContain('*Page.vue')
            ->and($this->extractor->patterns())->toContain('*Page.tsx')
            ->and($this->extractor->patterns())->toContain('*Page.jsx')
            ->and($this->extractor->patterns())->toContain('*Layout.vue')
            ->and($this->extractor->patterns())->toContain('*Layout.tsx');
    });

    it('extracts strings from the fixture', function () {
        $contents = file_get_contents(__DIR__.'/../../Fixtures/inertia/DashboardPage.vue');
        $strings = iterator_to_array($this->extractor->extract(makeInertiaFile(), $contents));

        $values = array_map(static fn ($s) => $s->value, $strings);

        expect($values)->toContainStringValue('Inertia page title')
            ->and($values)->toContainStringValue('pages.dashboard.welcome')
            ->and($values)->toContainStringValue('Inertia heading')
            ->and($values)->toContainStringValue('inertia.subtitle');
    });

    it('tags strings with the inertia extractor name', function () {
        $strings = iterator_to_array($this->extractor->extract(
            makeInertiaFile(),
            "\$t('one')",
        ));

        expect($strings[0]->extractor)->toBe('inertia');
    });
});
