<?php

declare(strict_types=1);

use Syriable\Localizer\CallableNormalizer;
use Syriable\Localizer\Contracts\Normalizer;
use Syriable\Localizer\Data\ExtractedString;
use Syriable\Localizer\Localizer;
use Syriable\Localizer\Support\ExtractorRegistry;

it('starts with no normalizers', function () {
    /** @var Localizer $engine */
    $engine = $this->app->make(Localizer::class);

    expect($engine->normalizers())->toBe([]);
});

it('accepts a Normalizer instance', function () {
    /** @var Localizer $engine */
    $engine = $this->app->make(Localizer::class);

    $normalizer = new class implements Normalizer
    {
        public function normalize(ExtractedString $string): ?ExtractedString
        {
            return $string;
        }
    };

    $engine->normalize($normalizer);

    expect($engine->normalizers())->toHaveCount(1)
        ->and($engine->normalizers()[0])->toBe($normalizer);
});

it('wraps a callable as a CallableNormalizer', function () {
    /** @var Localizer $engine */
    $engine = $this->app->make(Localizer::class);

    $engine->normalize(fn ($s) => $s);

    expect($engine->normalizers()[0])->toBeInstanceOf(CallableNormalizer::class);
});

it('withoutNormalizers clears the list', function () {
    /** @var Localizer $engine */
    $engine = $this->app->make(Localizer::class);

    $engine->normalize(fn ($s) => $s);
    $engine->withoutNormalizers();

    expect($engine->normalizers())->toBe([]);
});

it('exposes the extractor registry', function () {
    /** @var Localizer $engine */
    $engine = $this->app->make(Localizer::class);

    expect($engine->extractors())->toBeInstanceOf(ExtractorRegistry::class);
});

describe('PendingScan', function () {
    it('builds an immutable request', function () {
        /** @var Localizer $engine */
        $engine = $this->app->make(Localizer::class);

        $request = $engine->in('/a')->request();

        expect($request->paths)->toBe(['/a'])
            ->and($request->useCache)->toBeTrue();
    });

    it('accepts a list of paths', function () {
        /** @var Localizer $engine */
        $engine = $this->app->make(Localizer::class);

        $request = $engine->in(['/a', '/b'])->request();

        expect($request->paths)->toBe(['/a', '/b']);
    });

    it('appends exclusion patterns', function () {
        /** @var Localizer $engine */
        $engine = $this->app->make(Localizer::class);

        $request = $engine->in('/a')->exclude('**/node_modules/**')->request();

        expect($request->exclude)->toContain('**/node_modules/**');
    });

    it('appends multiple exclusion patterns', function () {
        /** @var Localizer $engine */
        $engine = $this->app->make(Localizer::class);

        $request = $engine->in('/a')->exclude(['**/a/**', '**/b/**'])->request();

        expect($request->exclude)->toContain('**/a/**')
            ->and($request->exclude)->toContain('**/b/**');
    });

    it('restricts extractors via only()', function () {
        /** @var Localizer $engine */
        $engine = $this->app->make(Localizer::class);

        $request = $engine->in('/a')->only(['blade', 'vue'])->request();

        expect($request->extractors)->toBe(['blade', 'vue']);
    });

    it('only() accepts a single string', function () {
        /** @var Localizer $engine */
        $engine = $this->app->make(Localizer::class);

        $request = $engine->in('/a')->only('blade')->request();

        expect($request->extractors)->toBe(['blade']);
    });

    it('fresh() disables caching', function () {
        /** @var Localizer $engine */
        $engine = $this->app->make(Localizer::class);

        $request = $engine->in('/a')->fresh()->request();

        expect($request->useCache)->toBeFalse();
    });

    it('is immutable — fluent methods return new instances', function () {
        /** @var Localizer $engine */
        $engine = $this->app->make(Localizer::class);

        $base = $engine->in('/a');
        $excluded = $base->exclude('**/x/**');

        expect($base)->not->toBe($excluded)
            ->and($base->request()->exclude)->toBe([])
            ->and($excluded->request()->exclude)->toContain('**/x/**');
    });
});
