<?php

declare(strict_types=1);

use function Syriable\Localizer\Tests\removeDir;
use function Syriable\Localizer\Tests\writeFile;

describe('GenerateCommand', function () {
    beforeEach(function () {
        config(['localizer.paths' => [$this->tempPath]]);
        config(['localizer.exclude' => []]);
        config(['app.locale' => 'en']);
        config(['localizer.generator.locales' => []]);
        config(['localizer.generator.strategy' => 'key']);

        // Ensure a clean lang directory for every test.
        removeDir(base_path('lang'));
    });

    afterEach(function () {
        removeDir(base_path('lang'));
    });

    it('exits successfully with no strings found', function () {
        $this->artisan('translations:generate', ['--locale' => 'en'])
            ->assertSuccessful();
    });

    it('generates a translation file for scanned blade strings', function () {
        writeFile($this->tempPath, 'welcome.blade.php', "{{ __('pagination.next') }}");

        $this->artisan('translations:generate', ['--locale' => 'en'])
            ->assertSuccessful();

        $langPath = base_path('lang/en/pagination.php');
        expect(file_exists($langPath))->toBeTrue();

        $loaded = include $langPath;
        expect($loaded)->toHaveKey('next');
    });

    it('--dry-run does not write files', function () {
        writeFile($this->tempPath, 'welcome.blade.php', "{{ __('pagination.next') }}");

        $this->artisan('translations:generate', ['--locale' => 'en', '--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('WOULD WRITE');

        expect(file_exists(base_path('lang/en/pagination.php')))->toBeFalse();
    });

    it('shows "no missing keys" when all keys already exist', function () {
        writeFile($this->tempPath, 'welcome.blade.php', "{{ __('pagination.next') }}");
        $langDir = base_path('lang/en');
        @mkdir($langDir, 0o755, true);
        file_put_contents($langDir.'/pagination.php', "<?php\nreturn ['next' => 'Next'];");

        $this->artisan('translations:generate', ['--locale' => 'en'])
            ->assertSuccessful()
            ->expectsOutputToContain('up to date');
    });

    it('--force overwrites existing values', function () {
        writeFile($this->tempPath, 'welcome.blade.php', "{{ __('pagination.next') }}");
        $langDir = base_path('lang/en');
        @mkdir($langDir, 0o755, true);
        file_put_contents($langDir.'/pagination.php', "<?php\nreturn ['next' => 'Custom'];");

        $this->artisan('translations:generate', ['--locale' => 'en', '--force' => true])
            ->assertSuccessful();

        $loaded = include $langDir.'/pagination.php';
        expect($loaded['next'])->toBe('next');
    });

    it('--strategy option controls value generation', function () {
        writeFile($this->tempPath, 'welcome.blade.php', "{{ __('pagination.next') }}");

        $this->artisan('translations:generate', ['--locale' => 'en', '--strategy' => 'empty'])
            ->assertSuccessful();

        $langPath = base_path('lang/en/pagination.php');
        expect(file_exists($langPath))->toBeTrue();
        $loaded = include $langPath;
        expect($loaded['next'])->toBe('');
    });

    it('--all-locales generates for each configured locale', function () {
        writeFile($this->tempPath, 'welcome.blade.php', "{{ __('pagination.next') }}");
        config(['localizer.generator.locales' => ['en', 'fr']]);

        $this->artisan('translations:generate', ['--all-locales' => true])
            ->assertSuccessful();

        expect(file_exists(base_path('lang/en/pagination.php')))->toBeTrue()
            ->and(file_exists(base_path('lang/fr/pagination.php')))->toBeTrue();
    });

    it('uses app.locale when no --locale is given', function () {
        writeFile($this->tempPath, 'welcome.blade.php', "{{ __('pagination.next') }}");
        config(['app.locale' => 'de']);

        $this->artisan('translations:generate')
            ->assertSuccessful();

        expect(file_exists(base_path('lang/de/pagination.php')))->toBeTrue();
    });

    it('--namespace restricts generation to vendor strings only', function () {
        writeFile($this->tempPath, 'welcome.blade.php',
            "{{ __('pagination.next') }}\n{{ __(\"acme::buttons.submit\") }}",
        );

        $this->artisan('translations:generate', ['--locale' => 'en', '--namespace' => 'acme'])
            ->assertSuccessful();

        expect(file_exists(base_path('lang/vendor/acme/en/buttons.php')))->toBeTrue()
            ->and(file_exists(base_path('lang/en/pagination.php')))->toBeFalse();
    });

    it('--fresh ignores the cache and re-scans', function () {
        writeFile($this->tempPath, 'welcome.blade.php', "{{ __('pagination.next') }}");

        $this->artisan('translations:generate', ['--locale' => 'en', '--fresh' => true])
            ->assertSuccessful();

        $loaded = include base_path('lang/en/pagination.php');
        expect($loaded)->toHaveKey('next');
    });

    it('succeeds with a warning when --all-locales has no configured locales', function () {
        config(['localizer.generator.locales' => []]);

        $this->artisan('translations:generate', ['--all-locales' => true])
            ->assertSuccessful();
    });
});
