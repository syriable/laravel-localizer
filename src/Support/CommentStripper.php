<?php

declare(strict_types=1);

namespace Syriable\Localizer\Support;

/**
 * Strips comment-like constructs from source code, replacing them with
 * whitespace so that byte offsets and line numbers remain intact for
 * callers that rely on offset-based line counting.
 *
 * String contexts are tracked to avoid stripping comment-like sequences
 * that appear inside quoted literals:
 *   - Single-quoted: `'...'`
 *   - Double-quoted: `"..."`
 *   - Backtick (JS template literals): `` `...` ``
 *
 * Comment styles stripped:
 *   - `// ...`        — C-style line comment (PHP, JS, TS)
 *   - `# ...`         — Shell-style line comment (PHP); `#[` is preserved
 *                       because it introduces a PHP 8 attribute, not a comment
 *   - `/* ... *\/`    — Block comment (PHP, JS, TS)
 *   - `{{-- ... --}}` — Blade template comment
 *   - `<!-- ... -->`  — HTML comment
 *
 * Each stripped comment is replaced by an equal number of space characters,
 * with embedded newlines kept intact, so that line-number counts derived
 * from the original content remain valid after stripping.
 */
final class CommentStripper
{
    public function strip(string $content): string
    {
        $result = '';
        $len = strlen($content);
        $i = 0;

        while ($i < $len) {
            $ch = $content[$i];

            // Track string contexts to avoid false positives inside strings.
            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $i = $this->consumeString($content, $i, $len, $ch, $result);

                continue;
            }

            // Blade comment: {{-- ... --}}
            if ($ch === '{' && $i + 3 < $len
                && $content[$i + 1] === '{'
                && $content[$i + 2] === '-'
                && $content[$i + 3] === '-'
            ) {
                $end = strpos($content, '--}}', $i + 4);
                if ($end !== false) {
                    $result .= $this->blankKeepNewlines($content, $i, $end + 4);
                    $i = $end + 4;

                    continue;
                }
            }

            // HTML comment: <!-- ... -->
            if ($ch === '<' && $i + 3 < $len
                && $content[$i + 1] === '!'
                && $content[$i + 2] === '-'
                && $content[$i + 3] === '-'
            ) {
                $end = strpos($content, '-->', $i + 4);
                if ($end !== false) {
                    $result .= $this->blankKeepNewlines($content, $i, $end + 3);
                    $i = $end + 3;

                    continue;
                }
            }

            // Block comment: /* ... */
            if ($ch === '/' && $i + 1 < $len && $content[$i + 1] === '*') {
                $end = strpos($content, '*/', $i + 2);
                if ($end !== false) {
                    $result .= $this->blankKeepNewlines($content, $i, $end + 2);
                    $i = $end + 2;

                    continue;
                }
            }

            // Single-line comment: //
            if ($ch === '/' && $i + 1 < $len && $content[$i + 1] === '/') {
                $end = strpos($content, "\n", $i + 2);
                $endI = $end !== false ? $end : $len;
                $result .= str_repeat(' ', $endI - $i);
                $i = $endI;

                continue;
            }

            // Single-line PHP comment: # (but not PHP 8 attribute #[)
            if ($ch === '#' && ($i + 1 >= $len || $content[$i + 1] !== '[')) {
                $end = strpos($content, "\n", $i + 1);
                $endI = $end !== false ? $end : $len;
                $result .= str_repeat(' ', $endI - $i);
                $i = $endI;

                continue;
            }

            $result .= $ch;
            $i++;
        }

        return $result;
    }

    /**
     * Consumes one quoted string starting at $i (the opening quote), appending
     * its raw content verbatim to $result. Returns the new cursor position
     * (just past the closing quote, or end-of-content if unterminated).
     */
    private function consumeString(string $content, int $i, int $len, string $quote, string &$result): int
    {
        $result .= $content[$i]; // opening quote
        $i++;

        while ($i < $len) {
            $c = $content[$i];
            $result .= $c;

            if ($c === '\\' && $i + 1 < $len) {
                $i++;
                $result .= $content[$i];
                $i++;

                continue;
            }

            if ($c === $quote) {
                return $i + 1;
            }

            $i++;
        }

        return $i;
    }

    /**
     * Returns a string of equal length to $content[$from..$to], with every
     * non-newline character replaced by a space. Preserving newlines keeps
     * line-number counts accurate in callers that use offset-based counting.
     */
    private function blankKeepNewlines(string $content, int $from, int $to): string
    {
        $out = '';

        for ($i = $from; $i < $to; $i++) {
            $out .= $content[$i] === "\n" ? "\n" : ' ';
        }

        return $out;
    }
}
