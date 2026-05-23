<?php

declare(strict_types=1);

namespace Syriable\Localizer\AI;

/**
 * Masks and restores Laravel-style placeholder tokens (`:word`) around AI
 * translation calls so the model does not accidentally translate them.
 *
 * Replace every `:name`, `:count`, etc. with an opaque `{{P0}}`, `{{P1}}`
 * token before sending text to the AI. After the translation comes back,
 * restore the original tokens by reversing the replacement map.
 *
 * Example:
 *   input  → "Welcome back, :name! You have :count messages."
 *   masked → "Welcome back, {{P0}}! You have {{P1}} messages."
 *   map    → ["{{P0}}" => ":name", "{{P1}}" => ":count"]
 */
final class PlaceholderMasker
{
    private const PATTERN = '/:([a-zA-Z_][a-zA-Z0-9_]*)/';

    /**
     * Replaces all `:word` placeholders with opaque `{{Pn}}` tokens.
     *
     * @return array{masked: string, map: array<string, string>}
     */
    public function mask(string $text): array
    {
        $map = [];
        $index = 0;

        $masked = preg_replace_callback(
            self::PATTERN,
            static function (array $matches) use (&$map, &$index): string {
                $token = '{{P'.$index.'}}';
                $map[$token] = $matches[0];
                $index++;

                return $token;
            },
            $text,
        );

        return [
            'masked' => $masked ?? $text,
            'map' => $map,
        ];
    }

    /**
     * Restores the original `:word` placeholders using the map from `mask()`.
     *
     * @param array<string, string> $map Token → original placeholder.
     */
    public function unmask(string $text, array $map): string
    {
        if ($map === []) {
            return $text;
        }

        return str_replace(array_keys($map), array_values($map), $text);
    }
}
