<?php

declare(strict_types=1);

namespace Syriable\Localizer\AI;

use Closure;

/**
 * Thin client for the Anthropic Messages API.
 *
 * Uses PHP's built-in cURL extension — no additional HTTP library needed.
 * An optional `$transport` closure can be injected to replace the real HTTP
 * call in tests or custom integrations. The closure signature must match:
 *
 *   function(string $url, array $headers, array $payload): string
 *
 * where the return value is the raw JSON response body.
 */
final class AiTranslationClient
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    private const ANTHROPIC_VERSION = '2023-06-01';

    /**
     * @var (Closure(string, array<string, string>, array<string, mixed>): string)|null
     */
    private readonly ?Closure $transport;

    /**
     * @param (Closure(string, array<string, string>, array<string, mixed>): string)|null $transport
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        ?Closure $transport = null,
    ) {
        $this->transport = $transport;
    }

    public function model(): string
    {
        return $this->model;
    }

    /**
     * Translates `$text` from `$sourceLocale` into `$targetLocale` using the
     * configured model and returns the translated string.
     *
     * The optional `$contextBlock` is injected verbatim into the prompt after
     * the rules section. Callers use it to pass placeholder descriptions
     * derived from call-site analysis.
     *
     * @throws \RuntimeException On HTTP failure or unexpected API response shape.
     */
    public function translate(string $text, string $sourceLocale, string $targetLocale, string $contextBlock = ''): string
    {
        $prompt = $this->buildPrompt($text, $sourceLocale, $targetLocale, $contextBlock);

        $payload = [
            'model' => $this->model,
            'max_tokens' => 1024,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        $headers = [
            'x-api-key' => $this->apiKey,
            'anthropic-version' => self::ANTHROPIC_VERSION,
            'content-type' => 'application/json',
        ];

        $body = $this->httpPost(self::API_URL, $headers, $payload);

        return $this->parseResponse($body);
    }

    private function buildPrompt(string $text, string $sourceLocale, string $targetLocale, string $contextBlock = ''): string
    {
        $context = $contextBlock !== '' ? "\n{$contextBlock}" : '';

        return <<<PROMPT
        You are a professional Laravel i18n translator.

        Translate the text below from {$sourceLocale} to {$targetLocale}.

        Rules (follow exactly):
        - Return ONLY the translated text — no explanation, no surrounding quotes.
        - Preserve placeholder tokens exactly as-is: {{P0}}, {{P1}}, {{P2}}, etc.
        - If the input is a dot-notation key (e.g. "auth.login.failed"), produce a natural {$targetLocale} phrase.
        - Match the capitalisation and punctuation style of the input.{$context}
        Text: {$text}
        PROMPT;
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>  $payload
     */
    private function httpPost(string $url, array $headers, array $payload): string
    {
        if ($this->transport !== null) {
            return ($this->transport)($url, $headers, $payload);
        }

        return $this->curlPost($url, $headers, $payload);
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>  $payload
     */
    private function curlPost(string $url, array $headers, array $payload): string
    {
        $ch = curl_init($url);

        if ($ch === false) {
            throw new \RuntimeException('Failed to initialise cURL.');
        }

        $headerLines = [];

        foreach ($headers as $name => $value) {
            $headerLines[] = "{$name}: {$value}";
        }

        $body = (string) json_encode($payload);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $result = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if (! is_string($result)) {
            throw new \RuntimeException("cURL request failed: {$error}");
        }

        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException("Anthropic API returned HTTP {$status}: {$result}");
        }

        return $result;
    }

    private function parseResponse(string $body): string
    {
        $data = json_decode($body, true);

        if (! is_array($data)) {
            throw new \RuntimeException('Anthropic API returned a non-JSON response.');
        }

        $content = $data['content'] ?? null;

        if (! is_array($content) || $content === []) {
            throw new \RuntimeException('Anthropic API response is missing the content array.');
        }

        $first = $content[0] ?? null;

        if (! is_array($first) || ! isset($first['text']) || ! is_string($first['text'])) {
            throw new \RuntimeException('Anthropic API response content item is missing the text field.');
        }

        return trim($first['text']);
    }
}
