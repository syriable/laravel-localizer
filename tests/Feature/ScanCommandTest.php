<?php

declare(strict_types=1);
use Syriable\Localizer\Data\ScanRequest;
use Syriable\Localizer\Facades\Localizer;

it('runs successfully with no files', function () {
    $this->artisan('localizer:scan')
        ->expectsOutputToContain('No translatable strings were found.')
        ->assertExitCode(0);
});

it('discovers and prints strings as a table', function () {
    $this->writeFixture('views/welcome.blade.php', "__('Welcome here')");

    $this->artisan('localizer:scan')
        ->expectsOutputToContain('Welcome here')
        ->assertExitCode(0);
});

it('prints a summary section', function () {
    $this->writeFixture('views/sum.blade.php', "__('sum me')");

    $this->artisan('localizer:scan')
        ->expectsOutputToContain('Files scanned')
        ->expectsOutputToContain('Strings (unique)')
        ->assertExitCode(0);
});

it('honors the --summary flag by showing the summary', function () {
    $this->writeFixture('views/only-summary.blade.php', "__('summarized string')");

    $this->artisan('localizer:scan --summary')
        ->expectsOutputToContain('Files scanned')
        ->expectsOutputToContain('Strings (unique)')
        ->assertExitCode(0);
});

it('outputs JSON with --json', function () {
    $this->writeFixture('views/jsonable.blade.php', "__('jsonable')");

    // NOTE: Laravel's `expectsOutputToContain` matches one buffered output
    // entry per expectation — so chaining multiple expectations against a
    // single `$this->line($json)` call doesn't work as it might seem. We
    // assert the most meaningful substring (the extracted value, which
    // proves both that JSON rendered AND that extraction succeeded).
    $this->artisan('localizer:scan --json')
        ->expectsOutputToContain('jsonable')
        ->assertExitCode(0);
});

it('accepts custom paths as arguments', function () {
    $custom = $this->tempPath.'/custom-path';
    mkdir($custom, 0o755, true);
    file_put_contents($custom.'/x.blade.php', "__('custom path string')");

    $this->artisan('localizer:scan '.$custom)
        ->expectsOutputToContain('custom path string')
        ->assertExitCode(0);
});

it('accepts --extractor to restrict scanning', function () {
    $this->writeFixture('views/restrict.blade.php', "__('only blade')");
    $this->writeFixture('app/Restrict.php', "<?php __('only php');");

    $exitCode = $this->artisan('localizer:scan --extractor=blade --json')
        ->assertExitCode(0)
        ->run();

    expect($exitCode)->toBe(0);

    // Re-run programmatically to inspect strings.
    $result = Localizer::scan(new ScanRequest(
        paths: [$this->tempPath],
        extractors: ['blade'],
        useCache: false,
    ));

    $values = array_map(static fn ($s) => $s->value, $result->strings);

    expect($values)->toContain('only blade')
        ->and($values)->not->toContain('only php');
});

it('honors --fresh by ignoring the cache', function () {
    $this->writeFixture('views/cache-bypass.blade.php', "__('bypass cache')");

    $this->artisan('localizer:scan')->assertExitCode(0);
    $this->artisan('localizer:scan --fresh --summary')
        ->expectsOutputToContain('Files from cache')
        ->assertExitCode(0);
});
