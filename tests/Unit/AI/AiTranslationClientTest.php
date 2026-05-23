<?php

declare(strict_types=1);

use Syriable\Localizer\AI\AiTranslationClient;

/**
 * Builds a client whose HTTP transport is replaced with a closure that
 * records the request and returns the given raw JSON body.
 *
 * @param array<string, mixed> $responseData
 */
function makeTestClient(array $responseData, ?callable $spy = null): AiTranslationClient
{
    return new AiTranslationClient(
        apiKey: 'test-key',
        model: 'claude-test',
        transport: function (string $url, array $headers, array $payload) use ($responseData, $spy): string {
            if ($spy !== null) {
                $spy($url, $headers, $payload);
            }

            return (string) json_encode($responseData);
        },
    );
}

describe('AiTranslationClient', function () {
    it('exposes the configured model name', function () {
        $client = new AiTranslationClient(apiKey: 'k', model: 'my-model');

        expect($client->model())->toBe('my-model');
    });

    it('returns the translated text from a successful response', function () {
        $client = makeTestClient([
            'content' => [['type' => 'text', 'text' => 'Bonjour le monde']],
        ]);

        expect($client->translate('Hello world', 'en', 'fr'))->toBe('Bonjour le monde');
    });

    it('trims leading and trailing whitespace from the response', function () {
        $client = makeTestClient([
            'content' => [['type' => 'text', 'text' => "  Hola  \n"]],
        ]);

        expect($client->translate('Hello', 'en', 'es'))->toBe('Hola');
    });

    it('passes the api key header to the transport', function () {
        $capturedHeaders = [];

        $client = new AiTranslationClient(
            apiKey: 'secret-key',
            model: 'test',
            transport: function (string $url, array $headers, array $payload) use (&$capturedHeaders): string {
                $capturedHeaders = $headers;

                return (string) json_encode(['content' => [['text' => 'ok']]]);
            },
        );

        $client->translate('hi', 'en', 'fr');

        expect($capturedHeaders['x-api-key'])->toBe('secret-key');
    });

    it('includes the model and message in the request payload', function () {
        $capturedPayload = [];

        $client = new AiTranslationClient(
            apiKey: 'k',
            model: 'claude-opus-4-7',
            transport: function (string $url, array $headers, array $payload) use (&$capturedPayload): string {
                $capturedPayload = $payload;

                return (string) json_encode(['content' => [['text' => 'ok']]]);
            },
        );

        $client->translate('hello', 'en', 'fr');

        expect($capturedPayload['model'])->toBe('claude-opus-4-7');
        expect($capturedPayload['messages'])->toHaveCount(1);
        expect($capturedPayload['messages'][0]['role'])->toBe('user');
        expect($capturedPayload['messages'][0]['content'])->toContain('hello');
    });

    it('throws a RuntimeException when the response is not JSON', function () {
        $client = new AiTranslationClient(
            apiKey: 'k',
            model: 'test',
            transport: fn (): string => 'not json at all',
        );

        expect(fn () => $client->translate('hi', 'en', 'fr'))
            ->toThrow(RuntimeException::class);
    });

    it('throws a RuntimeException when the content array is missing', function () {
        $client = makeTestClient(['id' => 'msg_xyz', 'type' => 'message']);

        expect(fn () => $client->translate('hi', 'en', 'fr'))
            ->toThrow(RuntimeException::class, 'content array');
    });

    it('throws a RuntimeException when the text field is missing', function () {
        $client = makeTestClient(['content' => [['type' => 'tool_use']]]);

        expect(fn () => $client->translate('hi', 'en', 'fr'))
            ->toThrow(RuntimeException::class, 'text field');
    });

    it('sends the source and target locale in the prompt', function () {
        $capturedContent = '';

        $client = new AiTranslationClient(
            apiKey: 'k',
            model: 'test',
            transport: function (string $url, array $headers, array $payload) use (&$capturedContent): string {
                $capturedContent = $payload['messages'][0]['content'] ?? '';

                return (string) json_encode(['content' => [['text' => 'ok']]]);
            },
        );

        $client->translate('greeting', 'en', 'de');

        expect($capturedContent)->toContain('en')
            ->and($capturedContent)->toContain('de');
    });
});
