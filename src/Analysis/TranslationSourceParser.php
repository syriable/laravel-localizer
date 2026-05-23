<?php

declare(strict_types=1);

namespace Syriable\Localizer\Analysis;

use Syriable\Localizer\Data\SourceLocation;
use Syriable\Localizer\Support\CallExtractor;
use Syriable\Localizer\Support\CommentStripper;

/**
 * Locates translation calls in PHP/Blade source code and extracts both
 * the literal key and the replacements array.
 *
 * This is a sibling of {@see CallExtractor},
 * which only extracts the first string argument. The analyzer pipeline
 * needs the second (or third, for *_choice variants) argument as well —
 * a key→PHP-expression map representing the placeholders.
 *
 * Calls that pass a dynamic key (`__($var)`), no array literal for the
 * replacements, or any other shape we can't parse statically are skipped.
 * Static analysis is necessarily incomplete; the goal is to capture the
 * vast majority of conventional call sites, not every possibility.
 *
 * Supported call shapes:
 *
 *   __('key', ['name' => $expr])
 *   trans('key', ['name' => $expr])
 *   `@lang`('key', ['name' => $expr])
 *   Lang::get('key', ['name' => $expr])
 *   trans_choice('key', $count, ['name' => $expr])
 *   Lang::choice('key', $count, ['name' => $expr])
 */
final class TranslationSourceParser
{
    public function __construct(
        private readonly CommentStripper $stripper = new CommentStripper,
    ) {}

    /**
     * Function names whose replacements array is the 2nd argument.
     *
     * `lang` is included to support the Blade `@lang(...)` directive; the
     * `@` prefix is preserved when reporting the function name (see
     * {@see resolveFunctionName()}).
     */
    private const SIMPLE_FUNCTIONS = ['__', 'trans', 'lang', 'Lang::get'];

    /**
     * Function names whose replacements array is the 3rd argument.
     */
    private const CHOICE_FUNCTIONS = ['trans_choice', 'Lang::choice'];

    /**
     * Discovers translation calls in source code.
     *
     * @return iterable<array{
     *     function: string,
     *     key: string,
     *     replacements: array<string, string>,
     *     location: SourceLocation
     * }>
     */
    public function parse(string $contents, string $filePath): iterable
    {
        $contents = $this->stripper->strip($contents);

        foreach ($this->findCallStarts($contents) as [$function, $afterParenOffset]) {
            $parsed = $this->parseCall($contents, $function, $afterParenOffset, $filePath);

            if ($parsed !== null) {
                yield $parsed;
            }
        }
    }

    /**
     * Finds every translation call start in the source.
     *
     * Yields tuples of [functionName, offsetJustAfterOpeningParen].
     *
     * @return iterable<array{0: string, 1: int}>
     */
    private function findCallStarts(string $contents): iterable
    {
        $allFunctions = array_merge(self::SIMPLE_FUNCTIONS, self::CHOICE_FUNCTIONS);

        $bareNames = array_filter($allFunctions, static fn ($f): bool => ! str_contains($f, '::'));
        $staticNames = array_filter($allFunctions, static fn ($f): bool => str_contains($f, '::'));

        $patterns = [];

        if ($bareNames !== []) {
            $alternation = implode('|', array_map(static fn ($f): string => preg_quote($f, '/'), $bareNames));
            $patterns[] = '/(?<![A-Za-z0-9_$])(@?)('.$alternation.')\s*\(/';
        }

        foreach ($staticNames as $staticName) {
            $patterns[] = '/(?<![A-Za-z0-9_$])('.preg_quote($staticName, '/').')\s*\(/';
        }

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE) === false) {
                continue;
            }

            foreach ($matches[0] as $i => $match) {
                [$text, $offset] = $match;

                $function = $this->resolveFunctionName($text);

                if ($function === null) {
                    continue;
                }

                yield [$function, $offset + strlen($text)];
            }
        }
    }

    private function resolveFunctionName(string $matchText): ?string
    {
        $trimmed = rtrim(substr($matchText, 0, -1));

        $trimmed = rtrim($trimmed);

        if ($trimmed === '') {
            return null;
        }

        return $trimmed;
    }

    /**
     * Parses a single discovered call into the result tuple.
     *
     * @return array{
     *     function: string,
     *     key: string,
     *     replacements: array<string, string>,
     *     location: SourceLocation
     * }|null
     */
    private function parseCall(string $contents, string $function, int $cursor, string $filePath): ?array
    {
        $length = strlen($contents);

        [$key, $cursor] = $this->extractStringArgument($contents, $cursor, $length);

        if ($key === null) {
            return null;
        }

        $skipExtraArg = in_array($this->canonicalise($function), self::CHOICE_FUNCTIONS, true);

        $cursor = $this->skipWhitespace($contents, $cursor, $length);

        if ($cursor >= $length || $contents[$cursor] !== ',') {
            return $this->makeResult($function, $key, [], $contents, $cursor, $filePath);
        }

        $cursor++;
        $cursor = $this->skipWhitespace($contents, $cursor, $length);

        if ($skipExtraArg) {
            $cursor = $this->skipExpression($contents, $cursor, $length);
            $cursor = $this->skipWhitespace($contents, $cursor, $length);

            if ($cursor >= $length || $contents[$cursor] !== ',') {
                return $this->makeResult($function, $key, [], $contents, $cursor, $filePath);
            }

            $cursor++;
            $cursor = $this->skipWhitespace($contents, $cursor, $length);
        }

        if ($cursor >= $length || $contents[$cursor] !== '[') {
            return $this->makeResult($function, $key, [], $contents, $cursor, $filePath);
        }

        $replacements = $this->extractArrayPairs($contents, $cursor, $length);

        return $this->makeResult($function, $key, $replacements, $contents, $cursor, $filePath);
    }

    /**
     * @param array<string, string> $replacements
     * @return array{
     *     function: string,
     *     key: string,
     *     replacements: array<string, string>,
     *     location: SourceLocation
     * }
     */
    private function makeResult(string $function, string $key, array $replacements, string $contents, int $cursor, string $filePath): array
    {
        $line = substr_count(substr($contents, 0, $cursor), "\n") + 1;

        return [
            'function' => $function,
            'key' => $key,
            'replacements' => $replacements,
            'location' => new SourceLocation($filePath, $line),
        ];
    }

    /**
     * Extracts the next string literal starting at the cursor (skipping
     * surrounding whitespace). Returns [unescapedValue, cursorAfterString].
     *
     * @return array{0: string|null, 1: int}
     */
    private function extractStringArgument(string $contents, int $cursor, int $length): array
    {
        $cursor = $this->skipWhitespace($contents, $cursor, $length);

        if ($cursor >= $length) {
            return [null, $cursor];
        }

        $quote = $contents[$cursor];

        if ($quote !== "'" && $quote !== '"') {
            return [null, $cursor];
        }

        $cursor++;
        $buffer = '';

        while ($cursor < $length) {
            $ch = $contents[$cursor];

            if ($ch === '\\' && $cursor + 1 < $length) {
                $next = $contents[$cursor + 1];

                $buffer .= match ($next) {
                    'n' => "\n",
                    't' => "\t",
                    'r' => "\r",
                    '\\' => '\\',
                    "'" => "'",
                    '"' => '"',
                    default => $ch.$next,
                };

                $cursor += 2;

                continue;
            }

            if ($ch === $quote) {
                return [$buffer, $cursor + 1];
            }

            $buffer .= $ch;
            $cursor++;
        }

        return [null, $cursor];
    }

    /**
     * Skips a single PHP expression starting at the cursor. Used to step
     * past the count argument of `trans_choice` / `Lang::choice`.
     */
    private function skipExpression(string $contents, int $cursor, int $length): int
    {
        $depth = 0;
        $string = null;
        $escape = false;

        while ($cursor < $length) {
            $ch = $contents[$cursor];

            if ($escape) {
                $escape = false;
                $cursor++;

                continue;
            }

            if ($string !== null) {
                if ($ch === '\\') {
                    $escape = true;
                } elseif ($ch === $string) {
                    $string = null;
                }

                $cursor++;

                continue;
            }

            if ($ch === "'" || $ch === '"') {
                $string = $ch;
                $cursor++;

                continue;
            }

            if ($ch === '(' || $ch === '[' || $ch === '{') {
                $depth++;
                $cursor++;

                continue;
            }

            if ($ch === ')' || $ch === ']' || $ch === '}') {
                if ($depth === 0) {
                    return $cursor;
                }

                $depth--;
                $cursor++;

                continue;
            }

            if ($ch === ',' && $depth === 0) {
                return $cursor;
            }

            $cursor++;
        }

        return $cursor;
    }

    /**
     * Extracts key → expression pairs from an array literal starting at
     * the opening `[`. Returns an empty array for any non-conforming shape.
     *
     * @return array<string, string>
     */
    private function extractArrayPairs(string $contents, int $cursor, int $length): array
    {
        if ($contents[$cursor] !== '[') {
            return [];
        }

        $cursor++;
        $result = [];

        while ($cursor < $length) {
            $cursor = $this->skipWhitespace($contents, $cursor, $length);

            if ($cursor >= $length) {
                return [];
            }

            if ($contents[$cursor] === ']') {
                return $result;
            }

            [$key, $cursor] = $this->extractStringArgument($contents, $cursor, $length);

            if ($key === null) {
                return $result;
            }

            $cursor = $this->skipWhitespace($contents, $cursor, $length);

            if ($cursor + 1 >= $length || $contents[$cursor] !== '=' || $contents[$cursor + 1] !== '>') {
                return $result;
            }

            $cursor += 2;
            $cursor = $this->skipWhitespace($contents, $cursor, $length);

            [$value, $cursor] = $this->extractValue($contents, $cursor, $length);

            if ($value === null) {
                return $result;
            }

            $result[$key] = $value;

            $cursor = $this->skipWhitespace($contents, $cursor, $length);

            if ($cursor < $length && $contents[$cursor] === ',') {
                $cursor++;

                continue;
            }

            $cursor = $this->skipWhitespace($contents, $cursor, $length);

            if ($cursor < $length && $contents[$cursor] === ']') {
                return $result;
            }

            return $result;
        }

        return $result;
    }

    /**
     * Captures one array-element value verbatim, stopping at the first
     * top-level `,` or `]`. String literals and nested brackets are honoured.
     *
     * @return array{0: string|null, 1: int}
     */
    private function extractValue(string $contents, int $cursor, int $length): array
    {
        $start = $cursor;
        $depth = 0;
        $string = null;
        $escape = false;

        while ($cursor < $length) {
            $ch = $contents[$cursor];

            if ($escape) {
                $escape = false;
                $cursor++;

                continue;
            }

            if ($string !== null) {
                if ($ch === '\\') {
                    $escape = true;
                } elseif ($ch === $string) {
                    $string = null;
                }

                $cursor++;

                continue;
            }

            if ($ch === "'" || $ch === '"') {
                $string = $ch;
                $cursor++;

                continue;
            }

            if ($ch === '(' || $ch === '[' || $ch === '{') {
                $depth++;
                $cursor++;

                continue;
            }

            if ($ch === ')' || $ch === '}') {
                $depth--;
                $cursor++;

                continue;
            }

            if ($ch === ']') {
                if ($depth === 0) {
                    return [rtrim(substr($contents, $start, $cursor - $start)), $cursor];
                }

                $depth--;
                $cursor++;

                continue;
            }

            if ($ch === ',' && $depth === 0) {
                return [rtrim(substr($contents, $start, $cursor - $start)), $cursor];
            }

            $cursor++;
        }

        return [null, $cursor];
    }

    private function skipWhitespace(string $contents, int $cursor, int $length): int
    {
        while ($cursor < $length && ctype_space($contents[$cursor])) {
            $cursor++;
        }

        return $cursor;
    }

    /**
     * Strips any leading `@` so that "@lang" and "lang" map to the same
     * function entry when looking up argument shapes.
     */
    private function canonicalise(string $function): string
    {
        return ltrim($function, '@');
    }
}
