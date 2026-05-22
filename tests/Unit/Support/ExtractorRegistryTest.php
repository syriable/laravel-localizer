<?php

declare(strict_types=1);

use Syriable\Localizer\Contracts\Extractor;
use Syriable\Localizer\Data\DiscoveredFile;
use Syriable\Localizer\Exceptions\UnknownExtractorException;
use Syriable\Localizer\Support\ExtractorRegistry;

function makeStubExtractor(string $name, array $patterns): Extractor
{
    return new class($name, $patterns) implements Extractor
    {
        /**
         * @param list<string> $patterns
         */
        public function __construct(
            private readonly string $extractorName,
            private readonly array $patterns,
        ) {}

        public function name(): string
        {
            return $this->extractorName;
        }

        public function patterns(): array
        {
            return $this->patterns;
        }

        public function extract(DiscoveredFile $file, string $contents): iterable
        {
            return [];
        }
    };
}

describe('ExtractorRegistry', function () {
    it('starts empty', function () {
        $registry = new ExtractorRegistry;

        expect($registry->count())->toBe(0)
            ->and($registry->all())->toBe([]);
    });

    it('registers an extractor by its name', function () {
        $registry = new ExtractorRegistry;
        $registry->register(makeStubExtractor('blade', ['*.blade.php']));

        expect($registry->has('blade'))->toBeTrue()
            ->and($registry->count())->toBe(1);
    });

    it('overwrites a previously-registered extractor of the same name', function () {
        $registry = new ExtractorRegistry;
        $first = makeStubExtractor('blade', ['*.blade.php']);
        $second = makeStubExtractor('blade', ['*.bld']);

        $registry->register($first);
        $registry->register($second);

        expect($registry->count())->toBe(1)
            ->and($registry->get('blade'))->toBe($second);
    });

    it('returns null when no extractor matches the basename', function () {
        $registry = new ExtractorRegistry;
        $registry->register(makeStubExtractor('blade', ['*.blade.php']));

        expect($registry->resolveForBasename('User.php'))->toBeNull();
    });

    it('resolves the first matching extractor in registration order', function () {
        $registry = new ExtractorRegistry;

        $blade = makeStubExtractor('blade', ['*.blade.php']);
        $php = makeStubExtractor('php', ['*.php']);

        $registry->register($blade);
        $registry->register($php);

        // `*.blade.php` matches first because it was registered first.
        expect($registry->resolveForBasename('welcome.blade.php'))->toBe($blade);
        // `*.blade.php` does not match a plain `.php` file, so PHP wins.
        expect($registry->resolveForBasename('User.php'))->toBe($php);
    });

    it('matches patterns case-insensitively', function () {
        $registry = new ExtractorRegistry;
        $registry->register(makeStubExtractor('vue', ['*.vue']));

        expect($registry->resolveForBasename('Dashboard.VUE'))->not->toBeNull();
    });

    it('throws when getting an unknown extractor', function () {
        (new ExtractorRegistry)->get('missing');
    })->throws(UnknownExtractorException::class, '[missing]');

    describe('only()', function () {
        it('returns a registry restricted to the named extractors', function () {
            $registry = new ExtractorRegistry;
            $registry->register(makeStubExtractor('blade', ['*.blade.php']));
            $registry->register(makeStubExtractor('php', ['*.php']));
            $registry->register(makeStubExtractor('vue', ['*.vue']));

            $subset = $registry->only(['blade', 'vue']);

            expect($subset->count())->toBe(2)
                ->and($subset->has('blade'))->toBeTrue()
                ->and($subset->has('vue'))->toBeTrue()
                ->and($subset->has('php'))->toBeFalse();
        });

        it('preserves the order of the names argument', function () {
            $registry = new ExtractorRegistry;
            $registry->register(makeStubExtractor('blade', ['*.blade.php']));
            $registry->register(makeStubExtractor('php', ['*.php']));

            $subset = $registry->only(['php', 'blade']);

            expect(array_keys($subset->all()))->toBe(['php', 'blade']);
        });

        it('throws when an unknown name is included', function () {
            $registry = new ExtractorRegistry;
            $registry->register(makeStubExtractor('blade', ['*.blade.php']));

            $registry->only(['blade', 'nonexistent']);
        })->throws(UnknownExtractorException::class);

        it('does not mutate the original registry', function () {
            $registry = new ExtractorRegistry;
            $registry->register(makeStubExtractor('blade', ['*.blade.php']));
            $registry->register(makeStubExtractor('php', ['*.php']));

            $registry->only(['blade']);

            expect($registry->count())->toBe(2);
        });
    });
});
