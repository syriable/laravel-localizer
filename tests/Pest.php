<?php

declare(strict_types=1);

use Syriable\Localizer\Data\ExtractedString;
use Syriable\Localizer\Data\ScanResult;
use Syriable\Localizer\Data\SourceLocation;
use Syriable\Localizer\Data\StringKind;
use Syriable\Localizer\Support\StringClassifier;
use Syriable\Localizer\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The test classes used by Pest. Feature tests boot the full Laravel
| application via Orchestra Testbench; unit tests run plain Pest with
| no application context.
|
*/
uses(TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| Custom expectations encapsulate frequently-asserted invariants and
| keep test bodies readable.
|
*/

expect()->extend('toBeShortKey', function (string $expectedFile) {
    expect($this->value->kind)->toBe(StringKind::ShortKey)
        ->and($this->value->file)->toBe($expectedFile);

    return $this;
});

expect()->extend('toBeJsonKey', function () {
    expect($this->value->kind)->toBe(StringKind::JsonKey)
        ->and($this->value->file)->toBeNull()
        ->and($this->value->key)->toBeNull();

    return $this;
});

expect()->extend('toHaveString', function (string $value) {
    /** @var ScanResult $result */
    $result = $this->value;

    $found = array_filter(
        $result->strings,
        static fn ($s): bool => $s->value === $value,
    );

    expect($found)->not->toBeEmpty(
        "Expected scan result to contain string [{$value}] but it did not.",
    );

    return $this;
});

expect()->extend('toContainStringValue', function (string $value) {
    /** @var array<int, string|ExtractedString> $items */
    $items = $this->value;

    $values = array_map(
        static fn ($item): string => $item instanceof ExtractedString
            ? $item->value
            : (string) $item,
        $items,
    );

    expect($values)->toContain($value);

    return $this;
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Builds an ExtractedString with reasonable defaults for tests.
 *
 * @param list<string> $directories
 */
function makeExtractedString(
    string $value = 'hello',
    ?StringKind $kind = null,
    string $extractor = 'test',
    string $path = '/tmp/file.php',
    int $line = 1,
    ?string $package = null,
    array $directories = [],
    ?string $file = null,
    ?string $key = null,
): ExtractedString {
    $kind ??= StringKind::JsonKey;

    // Convenience: if kind=ShortKey but no decomposition supplied, derive
    // from the value using the classifier (matches what extractors do).
    if ($kind === StringKind::ShortKey && $file === null) {
        $classifier = new StringClassifier;
        $package = $package ?? $classifier->packageFor($value);
        $directories = $directories === [] ? $classifier->directoriesFor($value) : $directories;
        $file = $classifier->fileFor($value);
        $key = $key ?? $classifier->keyFor($value);
    }

    return new ExtractedString(
        value: $value,
        kind: $kind,
        extractor: $extractor,
        location: new SourceLocation($path, $line),
        package: $package,
        directories: $directories,
        file: $file,
        key: $key,
    );
}
