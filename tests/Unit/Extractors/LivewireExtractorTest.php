<?php

declare(strict_types=1);

use Syriable\Localizer\Data\DiscoveredFile;
use Syriable\Localizer\Extractors\LivewireExtractor;
use Syriable\Localizer\Support\CallExtractor;
use Syriable\Localizer\Support\StringClassifier;

beforeEach(function () {
    $this->extractor = new LivewireExtractor(new CallExtractor, new StringClassifier);
});

function makeLivewireFile(string $path = '/abs/LivewireDashboard.php'): DiscoveredFile
{
    return new DiscoveredFile(
        absolutePath: $path,
        relativePath: basename($path),
        extension: 'php',
        extractor: 'livewire',
        size: 100,
    );
}

describe('LivewireExtractor', function () {
    it('identifies itself as "livewire"', function () {
        expect($this->extractor->name())->toBe('livewire');
    });

    it('matches Livewire 2 style filenames containing Livewire', function () {
        expect($this->extractor->patterns())->toContain('*Livewire*.php');
    });

    it('matches Livewire 3 Component suffix convention', function () {
        expect($this->extractor->patterns())->toContain('*Component.php');
    });

    it('extracts strings from the fixture', function () {
        $contents = file_get_contents(__DIR__.'/../../Fixtures/livewire/LivewireDashboard.php');
        $strings = iterator_to_array($this->extractor->extract(makeLivewireFile(), $contents));

        $values = array_map(static fn ($s) => $s->value, $strings);

        expect($values)->toContainStringValue('Livewire mounted successfully')
            ->and($values)->toContainStringValue('livewire.saved')
            ->and($values)->toContainStringValue('livewire.items');
    });

    it('tags strings with the livewire extractor name', function () {
        $strings = iterator_to_array($this->extractor->extract(
            makeLivewireFile(),
            "<?php __('one');",
        ));

        expect($strings[0]->extractor)->toBe('livewire');
    });

    it('matches a Livewire 3 *Component.php file via fnmatch', function () {
        $matched = false;

        foreach ($this->extractor->patterns() as $pattern) {
            if (fnmatch($pattern, 'ShowInvoicesComponent.php', FNM_CASEFOLD)) {
                $matched = true;
                break;
            }
        }

        expect($matched)->toBeTrue();
    });

    it('does not match a generic Livewire 3 name with no conventional suffix (Counter.php)', function () {
        $matched = false;

        foreach ($this->extractor->patterns() as $pattern) {
            if (fnmatch($pattern, 'Counter.php', FNM_CASEFOLD)) {
                $matched = true;
                break;
            }
        }

        // Counter.php has neither 'Livewire' nor 'Component' in its name.
        // It falls through to PhpExtractor, which handles it correctly.
        expect($matched)->toBeFalse();
    });
});
