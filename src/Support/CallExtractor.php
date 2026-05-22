<?php

declare(strict_types=1);

namespace Syriable\Localizer\Support;

/**
 * Robust extraction of the first string argument from named function calls.
 *
 * Many target languages (PHP, JS, TS, Vue) share the same pattern:
 *   translator_name('the key', ...other args)
 *
 * A naive regex like `/__\(['"]([^'"]+)['"]/` fails on escaped quotes,
 * concatenation, multi-line calls, and nested calls. This helper handles
 * those cases by manually scanning the content state-machine style.
 *
 * It identifies a name match, then walks forward through whitespace to
 * the opening parenthesis, then reads the first string literal (single
 * or double-quoted), honoring backslash escapes. Anything else (a
 * variable, a concatenation, a function call) is skipped — the engine
 * cannot statically resolve dynamic keys, and trying to would produce
 * false positives.
 */
final class CallExtractor
{
    /**
     * Finds all callsites of the named functions and yields the literal
     * first argument of each, along with the source offset of the
     * opening quote.
     *
     * @param  list<string>                                $names e.g. ['__', 'trans', 'trans_choice']
     * @return iterable<array{value: string, offset: int}>
     */
    public function extractCalls(string $contents, array $names): iterable
    {
        if ($names === []) {
            return;
        }

        $alternatives = array_map(static fn (string $n): string => preg_quote($n, '/'), $names);
        $pattern = '/(?<![A-Za-z0-9_$.])('.implode('|', $alternatives).')\s*\(/';

        if (preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE) === false) {
            return;
        }

        foreach ($matches[0] as $match) {
            $end = $match[1] + strlen($match[0]);

            $arg = $this->readFirstStringArgument($contents, $end);

            if ($arg !== null) {
                yield $arg;
            }
        }
    }

    /**
     * Reads the first string literal argument starting at $offset (which
     * is positioned just after an opening paren). Returns null if the
     * first non-whitespace token is not a quoted string.
     *
     * @return array{value: string, offset: int}|null
     */
    private function readFirstStringArgument(string $contents, int $offset): ?array
    {
        $length = strlen($contents);

        while ($offset < $length && ctype_space($contents[$offset])) {
            $offset++;
        }

        if ($offset >= $length) {
            return null;
        }

        $quote = $contents[$offset];

        if ($quote !== "'" && $quote !== '"' && $quote !== '`') {
            return null;
        }

        $quoteOffset = $offset;
        $offset++;
        $value = '';

        while ($offset < $length) {
            $char = $contents[$offset];

            if ($char === '\\' && $offset + 1 < $length) {
                $next = $contents[$offset + 1];
                $value .= match ($next) {
                    'n' => "\n",
                    't' => "\t",
                    'r' => "\r",
                    '\\' => '\\',
                    '\'' => "'",
                    '"' => '"',
                    '`' => '`',
                    default => $next,
                };
                $offset += 2;

                continue;
            }

            if ($char === $quote) {
                return ['value' => $value, 'offset' => $quoteOffset];
            }

            $value .= $char;
            $offset++;
        }

        return null;
    }

    /**
     * Counts newlines in $contents from start of file up to $offset.
     * Used by extractors to derive the 1-indexed line number.
     */
    public function lineFor(string $contents, int $offset): int
    {
        return substr_count($contents, "\n", 0, $offset) + 1;
    }
}
