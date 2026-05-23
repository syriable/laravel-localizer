<?php

declare(strict_types=1);

namespace Syriable\Localizer\Generator;

/**
 * Renders a nested PHP translation array to a properly formatted PHP file string.
 *
 * Output format:
 *
 * ```php
 * <?php
 *
 * declare(strict_types=1);
 *
 * return [
 *     'key' => 'value',
 *     'nested' => [
 *         'child' => 'value',
 *     ],
 * ];
 * ```
 *
 * Keys and string values are single-quoted. Non-string values (integers,
 * booleans) are rendered as bare PHP literals. Nested arrays are rendered
 * recursively with four-space indentation per level.
 */
final class TranslationPhpRenderer
{
    /**
     * @param array<string, mixed> $data
     */
    public function render(array $data): string
    {
        $body = $this->renderArray($data, indent: 1);

        return "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n{$body}];\n";
    }

    /**
     * @param array<string, mixed> $array
     */
    private function renderArray(array $array, int $indent): string
    {
        $pad = str_repeat('    ', $indent);
        $lines = '';

        foreach ($array as $key => $value) {
            $renderedKey = "'{$this->escapeString((string) $key)}'";

            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $nested = $this->renderArray($value, $indent + 1);
                $lines .= "{$pad}{$renderedKey} => [\n{$nested}{$pad}],\n";
            } elseif (is_string($value)) {
                $lines .= "{$pad}{$renderedKey} => '{$this->escapeString($value)}',\n";
            } elseif (is_bool($value)) {
                $lines .= "{$pad}{$renderedKey} => ".($value ? 'true' : 'false').",\n";
            } elseif (is_int($value) || is_float($value)) {
                $lines .= "{$pad}{$renderedKey} => {$value},\n";
            } elseif ($value === null) {
                $lines .= "{$pad}{$renderedKey} => null,\n";
            }
        }

        return $lines;
    }

    private function escapeString(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }
}
