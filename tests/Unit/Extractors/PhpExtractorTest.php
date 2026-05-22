<?php

declare(strict_types=1);

use Syriable\Localizer\Data\DiscoveredFile;
use Syriable\Localizer\Extractors\PhpExtractor;
use Syriable\Localizer\Support\CallExtractor;
use Syriable\Localizer\Support\StringClassifier;

beforeEach(function () {
    $this->extractor = new PhpExtractor(new CallExtractor, new StringClassifier);
});

function makePhpFile(string $path = '/abs/User.php'): DiscoveredFile
{
    return new DiscoveredFile(
        absolutePath: $path,
        relativePath: basename($path),
        extension: 'php',
        extractor: 'php',
        size: 100,
    );
}

describe('PhpExtractor', function () {
    it('identifies itself as "php"', function () {
        expect($this->extractor->name())->toBe('php');
    });

    it('matches *.php files', function () {
        expect($this->extractor->patterns())->toBe(['*.php']);
    });

    it('extracts strings from the fixture file', function () {
        $contents = file_get_contents(__DIR__.'/../../Fixtures/php/WelcomeNotification.php');
        $strings = iterator_to_array($this->extractor->extract(makePhpFile(), $contents));

        $values = array_map(static fn ($s) => $s->value, $strings);

        expect($values)->toContainStringValue('Welcome to our application')
            ->and($values)->toContainStringValue('greetings.formal')
            ->and($values)->toContainStringValue('emails.unread_count')
            ->and($values)->toContainStringValue('errors.fallback');
    });

    it('ignores dynamic calls', function () {
        $contents = file_get_contents(__DIR__.'/../../Fixtures/php/WelcomeNotification.php');
        $strings = iterator_to_array($this->extractor->extract(makePhpFile(), $contents));

        $values = array_map(static fn ($s) => $s->value, $strings);

        expect($values)->not->toContain('$key');
    });

    it('extracts trans_choice calls', function () {
        $strings = iterator_to_array($this->extractor->extract(
            makePhpFile(),
            "<?php trans_choice('items.count', \$n);",
        ));

        expect($strings)->toHaveCount(1)
            ->and($strings[0]->value)->toBe('items.count');
    });

    it('extracts Lang::get calls', function () {
        $strings = iterator_to_array($this->extractor->extract(
            makePhpFile(),
            "<?php Lang::get('errors.timeout');",
        ));

        expect($strings)->toHaveCount(1)
            ->and($strings[0]->value)->toBe('errors.timeout');
    });

    it('extracts Lang::choice calls', function () {
        $strings = iterator_to_array($this->extractor->extract(
            makePhpFile(),
            "<?php Lang::choice('errors.retries', 3);",
        ));

        expect($strings)->toHaveCount(1)
            ->and($strings[0]->value)->toBe('errors.retries');
    });

    it('tags every extraction with the php name', function () {
        $strings = iterator_to_array($this->extractor->extract(
            makePhpFile(),
            "<?php __('one'); trans('two');",
        ));

        foreach ($strings as $s) {
            expect($s->extractor)->toBe('php');
        }
    });
});
