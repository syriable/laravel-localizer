<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Syriable\Localizer\AI\AiTranslationClient;
use Syriable\Localizer\Generator\Strategies\AiGenerationStrategy;

use function Syriable\Localizer\Tests\removeDir;
use function Syriable\Localizer\Tests\writeFile;

describe('translations:generate --strategy=ai', function () {
    beforeEach(function () {
        config(['localizer.paths' => [$this->tempPath]]);
        config(['localizer.exclude' => []]);
        config(['app.locale' => 'en']);
        config(['localizer.ai.source_locale' => 'en']);
        config(['localizer.ai.cache_path' => $this->tempPath.'/ai-cache.json']);

        removeDir(base_path('lang'));

        $translationCount = 0;

        $this->app->singleton(AiTranslationClient::class, fn (): AiTranslationClient => new AiTranslationClient(
            apiKey: 'test-key',
            model: 'claude-test',
            transport: function (string $url, array $headers, array $payload) use (&$translationCount): string {
                $translationCount++;
                $content = $payload['messages'][0]['content'] ?? '';

                $text = 'AI translated';

                if (is_string($content) && str_contains($content, 'next')) {
                    $text = 'Suivant';
                }

                if (is_string($content) && str_contains($content, 'Bonjour')) {
                    $text = 'Bonjour le monde';
                }

                return (string) json_encode(['content' => [['text' => $text]]]);
            },
        ));

        $this->app->forgetInstance(AiGenerationStrategy::class);
    });

    afterEach(function () {
        removeDir(base_path('lang'));
    });

    it('generates translated values using the ai strategy', function () {
        writeFile($this->tempPath, 'app.blade.php', "{{ __('pagination.next') }}");

        $this->artisan('localizer:generate', ['--locale' => 'fr', '--strategy' => 'ai'])
            ->assertSuccessful();

        $langPath = base_path('lang/fr/pagination.php');
        expect(file_exists($langPath))->toBeTrue();

        $loaded = include $langPath;
        expect($loaded)->toHaveKey('next');
        expect($loaded['next'])->toBe('Suivant');
    });

    it('falls back to humanized values when source and target locale match', function () {
        writeFile($this->tempPath, 'app.blade.php', "{{ __('pagination.next') }}");

        $this->artisan('localizer:generate', ['--locale' => 'en', '--strategy' => 'ai'])
            ->assertSuccessful();

        $loaded = include base_path('lang/en/pagination.php');
        expect($loaded['next'])->toBe('Next');
    });

    it('caches translations and does not duplicate API calls', function () {
        $callCount = 0;

        $this->app->singleton(AiTranslationClient::class, function () use (&$callCount): AiTranslationClient {
            return new AiTranslationClient(
                apiKey: 'k',
                model: 'claude-test',
                transport: function () use (&$callCount): string {
                    $callCount++;

                    return (string) json_encode(['content' => [['text' => 'Suivant']]]);
                },
            );
        });

        $this->app->forgetInstance(AiGenerationStrategy::class);

        writeFile($this->tempPath, 'app.blade.php', "{{ __('pagination.next') }}");

        Artisan::call('localizer:generate', ['--locale' => 'fr', '--strategy' => 'ai']);
        Artisan::call('localizer:generate', ['--locale' => 'fr', '--strategy' => 'ai', '--force' => true]);

        expect($callCount)->toBe(1);
    });

    it('translates json-key strings into the target locale json file', function () {
        writeFile($this->tempPath, 'app.blade.php', "{{ __('Bonjour') }}");

        $this->artisan('localizer:generate', ['--locale' => 'fr', '--strategy' => 'ai'])
            ->assertSuccessful();

        $jsonPath = base_path('lang/fr.json');
        expect(file_exists($jsonPath))->toBeTrue();

        $data = json_decode((string) file_get_contents($jsonPath), true);
        expect($data)->toHaveKey('Bonjour');
        expect($data['Bonjour'])->toBe('Bonjour le monde');
    });
});
