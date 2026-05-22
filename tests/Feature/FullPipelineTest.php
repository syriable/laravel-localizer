<?php

declare(strict_types=1);

use Syriable\Localizer\Contracts\ScanCache;
use Syriable\Localizer\Data\ScanRequest;
use Syriable\Localizer\Events\ScanCompleted;
use Syriable\Localizer\Events\ScanStarted;
use Syriable\Localizer\Facades\Localizer;

it('returns an empty result when no files are present', function () {
    $result = Localizer::scan();

    expect($result->strings)->toBe([])
        ->and($result->filesScanned)->toBe(0)
        ->and($result->filesFromCache)->toBe(0);
});

it('extracts from a real blade fixture written to disk', function () {
    $this->copyFixture('blade/sample.blade.php', 'views/sample.blade.php');

    $result = Localizer::scan();

    expect($result->count())->toBeGreaterThan(0);
    expect($result)->toHaveString('Welcome back');
    expect($result)->toHaveString('pagination.next');
});

it('classifies short keys and json keys correctly', function () {
    $this->writeFixture(
        'views/mixed.blade.php',
        "@lang('pagination.next') {{ __('Welcome back') }}",
    );

    $result = Localizer::scan();
    $groups = $result->groupedByKind();

    expect($groups['short_key'])->toContainStringValue('pagination.next');
    expect($groups['json_key'])->toContainStringValue('Welcome back');
});

it('skips files matching exclusion patterns', function () {
    $this->writeFixture('views/keep.blade.php', "__('keep me')");
    $this->writeFixture('node_modules/skip.blade.php', "__('skip me')");

    config()->set('localizer.exclude', ['**/node_modules/**']);

    $result = Localizer::scan();

    expect($result)->toHaveString('keep me');
    expect($result->strings)->not->toContainStringValue('skip me');
});

it('respects extractor restrictions', function () {
    $this->copyFixture('blade/sample.blade.php', 'views/sample.blade.php');
    $this->copyFixture('php/WelcomeNotification.php', 'app/WelcomeNotification.php');

    $result = Localizer::scan(new ScanRequest(
        paths: [$this->tempPath],
        extractors: ['blade'],
    ));

    foreach ($result->strings as $string) {
        expect($string->extractor)->toBe('blade');
    }
});

it('uses the cache on the second scan to skip unchanged files', function () {
    $this->writeFixture('views/cached.blade.php', "__('cached value')");

    $first = Localizer::scan();
    $second = Localizer::scan();

    expect($first->filesFromCache)->toBe(0)
        ->and($second->filesFromCache)->toBe(1)
        ->and($second->filesFresh())->toBe(0);
});

it('re-extracts when a file changes', function () {
    $path = $this->writeFixture('views/mutable.blade.php', "__('original')");

    Localizer::scan();

    file_put_contents($path, "__('updated')");

    $second = Localizer::scan();

    expect($second->filesFresh())->toBe(1)
        ->and($second)->toHaveString('updated');
});

it('with fresh() ignores the cache', function () {
    $this->writeFixture('views/fresh.blade.php', "__('always fresh')");

    Localizer::scan();
    $result = Localizer::scan(new ScanRequest(paths: [$this->tempPath], useCache: false));

    expect($result->filesFresh())->toBe(1);
});

it('persists the cache to the configured path', function () {
    $this->writeFixture('views/persist.blade.php', "__('persist')");

    Localizer::scan();

    expect(file_exists(config('localizer.cache.path')))->toBeTrue();
});

it('respects the cache disabled flag', function () {
    config()->set('localizer.cache.enabled', false);
    $this->app->forgetInstance(ScanCache::class);
    $this->app->forgetInstance(Syriable\Localizer\Localizer::class);

    $this->writeFixture('views/no-cache.blade.php', "__('no cache')");

    Localizer::scan();
    $second = Localizer::scan();

    expect($second->filesFromCache)->toBe(0);
});

it('fires ScanStarted before discovery', function () {
    $this->writeFixture('views/event.blade.php', "__('hi')");

    $captured = null;
    $this->app['events']->listen(ScanStarted::class, function ($e) use (&$captured): void {
        $captured = $e;
    });

    Localizer::scan();

    expect($captured)->not->toBeNull();
});

it('fires ScanCompleted with the final result', function () {
    $this->writeFixture('views/complete.blade.php', "__('complete event')");

    $captured = null;
    $this->app['events']->listen(ScanCompleted::class, function ($e) use (&$captured): void {
        $captured = $e;
    });

    Localizer::scan();

    expect($captured)->not->toBeNull()
        ->and($captured->result->strings)->not->toBe([]);
});

it('applies registered normalizers', function () {
    $this->writeFixture('views/norm.blade.php', "__('drop me') __('keep me')");

    Localizer::normalize(function ($string) {
        return $string->value === 'drop me' ? null : $string;
    });

    $result = Localizer::scan();

    expect($result)->toHaveString('keep me');
    expect($result->strings)->not->toContainStringValue('drop me');
});

it('produces deterministic results across runs', function () {
    $this->copyFixture('blade/sample.blade.php', 'views/sample.blade.php');

    $first = Localizer::scan();
    $second = Localizer::scan();

    expect($first->toArray()['strings'])->toBe($second->toArray()['strings']);
});

it('scans multiple extractors in a single pass', function () {
    $this->copyFixture('blade/sample.blade.php', 'views/blade.blade.php');
    $this->copyFixture('vue/Dashboard.vue', 'resources/js/Dashboard.vue');
    $this->copyFixture('js/greetings.js', 'resources/js/greetings.js');

    $result = Localizer::scan();
    $extractors = array_unique(array_map(static fn ($s) => $s->extractor, $result->strings));

    expect($extractors)->toContain('blade')
        ->and($extractors)->toContain('vue')
        ->and($extractors)->toContain('javascript');
});

it('records duration in milliseconds', function () {
    $this->writeFixture('views/timing.blade.php', "__('timing')");

    $result = Localizer::scan();

    expect($result->durationMs)->toBeGreaterThan(0.0);
});

it('exposes a fluent builder via in()', function () {
    $this->writeFixture('views/fluent.blade.php', "__('fluent')");

    $result = Localizer::in($this->tempPath)
        ->only('blade')
        ->scan();

    expect($result)->toHaveString('fluent');
});

it('counts files scanned including both fresh and cached', function () {
    $this->writeFixture('a/a.blade.php', "__('a')");
    $this->writeFixture('b/b.blade.php', "__('b')");

    Localizer::scan();
    $second = Localizer::scan();

    expect($second->filesScanned)->toBe(2)
        ->and($second->filesFromCache)->toBe(2);
});

it('records extensions that had no matching extractor', function () {
    // Two .blade.php files (which DO match) and three "stray" files
    // with extensions no extractor handles. The stray ones should be
    // reported on $result->skippedExtensions; the .blade.php ones
    // should not appear there.
    $this->writeFixture('views/welcome.blade.php', "__('Welcome')");
    $this->writeFixture('views/home.blade.php', "__('Home')");
    $this->writeFixture('data/config.toml', 'key = "value"');
    $this->writeFixture('data/readme.md', '# Notes');
    $this->writeFixture('data/notes.md', '# More');

    $result = Localizer::scan();

    expect($result->skippedExtensions)
        ->toHaveKey('toml')
        ->and($result->skippedExtensions)->toHaveKey('md')
        ->and($result->skippedExtensions['toml'])->toBe(1)
        ->and($result->skippedExtensions['md'])->toBe(2)
        ->and($result->skippedExtensions)->not->toHaveKey('php');
});

it('reports an empty skippedExtensions map when every file has an extractor', function () {
    $this->writeFixture('views/a.blade.php', "__('A')");
    $this->writeFixture('views/b.blade.php', "__('B')");

    $result = Localizer::scan();

    expect($result->skippedExtensions)->toBe([]);
});

it('decomposes profile/buttons.submit.label into directories + file + key', function () {
    $this->writeFixture(
        'views/profile-test.blade.php',
        "{{ __('profile/buttons.submit.label') }}",
    );

    $result = Localizer::scan();

    expect($result->strings)->toHaveCount(1);

    $string = $result->strings[0];

    expect($string->value)->toBe('profile/buttons.submit.label')
        ->and($string->kind->value)->toBe('short_key')
        ->and($string->package)->toBeNull()
        ->and($string->directories)->toBe(['profile'])
        ->and($string->file)->toBe('buttons')
        ->and($string->key)->toBe('submit.label')
        ->and($string->filePath())->toBe('profile/buttons.php');
});

it('decomposes courier::messages.welcome (packaged, no directories)', function () {
    $this->writeFixture(
        'views/courier-test.blade.php',
        "{{ __('courier::messages.welcome') }}",
    );

    $result = Localizer::scan();

    expect($result->strings)->toHaveCount(1);

    $string = $result->strings[0];

    expect($string->value)->toBe('courier::messages.welcome')
        ->and($string->kind->value)->toBe('short_key')
        ->and($string->package)->toBe('courier')
        ->and($string->directories)->toBe([])
        ->and($string->file)->toBe('messages')
        ->and($string->key)->toBe('welcome')
        ->and($string->filePath())->toBe('messages.php');
});

it('decomposes pagination.next (plain key, no package, no directories)', function () {
    $this->writeFixture(
        'views/plain.blade.php',
        "{{ __('pagination.next') }}",
    );

    $result = Localizer::scan();

    expect($result->strings)->toHaveCount(1);

    $string = $result->strings[0];

    expect($string->kind->value)->toBe('short_key')
        ->and($string->package)->toBeNull()
        ->and($string->directories)->toBe([])
        ->and($string->file)->toBe('pagination')
        ->and($string->key)->toBe('next')
        ->and($string->filePath())->toBe('pagination.php');
});

it('decomposes syriable::profile/buttons.submit.label (package + directory + file + key)', function () {
    $this->writeFixture(
        'views/compound.blade.php',
        "{{ __('syriable::profile/buttons.submit.label') }}",
    );

    $result = Localizer::scan();

    expect($result->strings)->toHaveCount(1);

    $string = $result->strings[0];

    expect($string->value)->toBe('syriable::profile/buttons.submit.label')
        ->and($string->kind->value)->toBe('short_key')
        ->and($string->package)->toBe('syriable')
        ->and($string->directories)->toBe(['profile'])
        ->and($string->file)->toBe('buttons')
        ->and($string->key)->toBe('submit.label')
        ->and($string->filePath())->toBe('profile/buttons.php');
});

it('decomposes deeply nested directory chains (profile/button/form/icon.submit.label)', function () {
    $this->writeFixture(
        'views/deep.blade.php',
        "{{ __('profile/button/form/icon.submit.label') }}",
    );

    $result = Localizer::scan();

    expect($result->strings)->toHaveCount(1);

    $string = $result->strings[0];

    expect($string->value)->toBe('profile/button/form/icon.submit.label')
        ->and($string->kind->value)->toBe('short_key')
        ->and($string->package)->toBeNull()
        ->and($string->directories)->toBe(['profile', 'button', 'form'])
        ->and($string->file)->toBe('icon')
        ->and($string->key)->toBe('submit.label')
        ->and($string->filePath())->toBe('profile/button/form/icon.php');
});

it('decomposes syriable::profile/button/form/icon.submit.label (deep + packaged)', function () {
    $this->writeFixture(
        'views/deep-pkg.blade.php',
        "{{ __('syriable::profile/button/form/icon.submit.label') }}",
    );

    $result = Localizer::scan();

    expect($result->strings)->toHaveCount(1);

    $string = $result->strings[0];

    expect($string->value)->toBe('syriable::profile/button/form/icon.submit.label')
        ->and($string->kind->value)->toBe('short_key')
        ->and($string->package)->toBe('syriable')
        ->and($string->directories)->toBe(['profile', 'button', 'form'])
        ->and($string->file)->toBe('icon')
        ->and($string->key)->toBe('submit.label')
        ->and($string->filePath())->toBe('profile/button/form/icon.php');
});
