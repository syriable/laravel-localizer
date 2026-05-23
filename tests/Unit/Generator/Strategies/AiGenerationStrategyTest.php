<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Syriable\Localizer\AI\AiTranslationCache;
use Syriable\Localizer\AI\AiTranslationClient;
use Syriable\Localizer\AI\PlaceholderMasker;
use Syriable\Localizer\Generator\Strategies\AiGenerationStrategy;
use Syriable\Localizer\Generator\Strategies\HumanizedStrategy;
use Syriable\Localizer\Support\AtomicWriter;

/**
 * Creates an AiTranslationClient whose HTTP call returns the given translation.
 */
function makeClient(string $translation, string $model = 'test-model'): AiTranslationClient
{
    return new AiTranslationClient(
        apiKey: 'test',
        model: $model,
        transport: fn (): string => (string) json_encode([
            'content' => [['text' => $translation]],
        ]),
    );
}

/**
 * Creates a temporary AiTranslationCache backed by a scratch directory.
 */
function makeTempCache(): AiTranslationCache
{
    static $index = 0;
    $path = sys_get_temp_dir().'/ai-strategy-test-'.(++$index).'-'.bin2hex(random_bytes(3)).'/cache.json';

    return new AiTranslationCache(
        files: new Filesystem,
        writer: new AtomicWriter(new Filesystem),
        path: $path,
    );
}

describe('AiGenerationStrategy', function () {
    it('returns its name as "ai"', function () {
        $strategy = new AiGenerationStrategy(
            client: makeClient(''),
            cache: makeTempCache(),
            masker: new PlaceholderMasker,
            fallback: new HumanizedStrategy,
            sourceLocale: 'en',
        );

        expect($strategy->name())->toBe('ai');
    });

    it('falls back when source and target locale are the same', function () {
        $called = false;

        $client = new AiTranslationClient(
            apiKey: 'k',
            model: 'test',
            transport: function () use (&$called): string {
                $called = true;

                return (string) json_encode(['content' => [['text' => 'X']]]);
            },
        );

        $strategy = new AiGenerationStrategy(
            client: $client,
            cache: makeTempCache(),
            masker: new PlaceholderMasker,
            fallback: new HumanizedStrategy,
            sourceLocale: 'en',
            targetLocale: 'en',
        );

        $result = $strategy->generate('auth.login.failed');

        expect($called)->toBeFalse();
        expect($result)->toBe('Failed');
    });

    it('calls the API and returns the translation', function () {
        $strategy = new AiGenerationStrategy(
            client: makeClient('Connexion'),
            cache: makeTempCache(),
            masker: new PlaceholderMasker,
            fallback: new HumanizedStrategy,
            sourceLocale: 'en',
            targetLocale: 'fr',
        );

        expect($strategy->generate('auth.login.failed'))->toBe('Connexion');
    });

    it('returns a cached translation without calling the API again', function () {
        $callCount = 0;

        $client = new AiTranslationClient(
            apiKey: 'k',
            model: 'test-model',
            transport: function () use (&$callCount): string {
                $callCount++;

                return (string) json_encode(['content' => [['text' => 'Bonjour']]]);
            },
        );

        $cache = makeTempCache();
        $strategy = new AiGenerationStrategy(
            client: $client,
            cache: $cache,
            masker: new PlaceholderMasker,
            fallback: new HumanizedStrategy,
            sourceLocale: 'en',
            targetLocale: 'fr',
        );

        $first = $strategy->generate('hello');
        $second = $strategy->generate('hello');

        expect($first)->toBe('Bonjour');
        expect($second)->toBe('Bonjour');
        expect($callCount)->toBe(1);
    });

    it('falls back on API error', function () {
        $client = new AiTranslationClient(
            apiKey: 'k',
            model: 'test',
            transport: fn (): string => 'bad json {{',
        );

        $strategy = new AiGenerationStrategy(
            client: $client,
            cache: makeTempCache(),
            masker: new PlaceholderMasker,
            fallback: new HumanizedStrategy,
            sourceLocale: 'en',
            targetLocale: 'fr',
        );

        $result = $strategy->generate('auth.login.failed');

        expect($result)->toBe('Failed');
    });

    it('masks placeholders before the API call and restores them after', function () {
        $receivedText = '';

        $client = new AiTranslationClient(
            apiKey: 'k',
            model: 'test',
            transport: function (string $url, array $headers, array $payload) use (&$receivedText): string {
                $receivedText = $payload['messages'][0]['content'];

                return (string) json_encode(['content' => [['text' => 'Bonjour, {{P0}}!']]]);
            },
        );

        $strategy = new AiGenerationStrategy(
            client: $client,
            cache: makeTempCache(),
            masker: new PlaceholderMasker,
            fallback: new HumanizedStrategy,
            sourceLocale: 'en',
            targetLocale: 'fr',
        );

        $result = $strategy->generate('Hello, :name!');

        // The prompt must contain the masked token but must NOT pass the raw `:name`
        // as the value to translate (it appears only in the context block as metadata).
        expect($receivedText)->toContain('{{P0}}');
        expect($receivedText)->toContain('Text: Hello, {{P0}}!');
        expect($receivedText)->not->toContain('Text: Hello, :name!');

        expect($result)->toBe('Bonjour, :name!');
    });

    it('withLocale returns a new instance with the updated locale', function () {
        $strategy = new AiGenerationStrategy(
            client: makeClient('x'),
            cache: makeTempCache(),
            masker: new PlaceholderMasker,
            fallback: new HumanizedStrategy,
            sourceLocale: 'en',
            targetLocale: 'en',
        );

        $fr = $strategy->withLocale('fr');

        expect($fr)->not->toBe($strategy);
        expect($fr->name())->toBe('ai');
    });

    it('withLocale does not mutate the original instance', function () {
        $apiCallCount = 0;

        $client = new AiTranslationClient(
            apiKey: 'k',
            model: 'test-model',
            transport: function () use (&$apiCallCount): string {
                $apiCallCount++;

                return (string) json_encode(['content' => [['text' => 'Translated']]]);
            },
        );

        $base = new AiGenerationStrategy(
            client: $client,
            cache: makeTempCache(),
            masker: new PlaceholderMasker,
            fallback: new HumanizedStrategy,
            sourceLocale: 'en',
            targetLocale: 'en',
        );

        $fr = $base->withLocale('fr');
        $fr->generate('hello');

        $base->generate('hello');

        expect($apiCallCount)->toBe(1);
    });
});
