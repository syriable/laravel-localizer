<?php

declare(strict_types=1);

namespace Syriable\Localizer\Support;

/**
 * Glob-style path matching for exclusion patterns.
 *
 * Supports the `**` wildcard (any number of path segments) in addition
 * to the standard fnmatch semantics. Matching is performed against the
 * normalized forward-slash form of the path.
 */
final class PathMatcher
{
    /**
     * @param list<string> $patterns
     */
    public function matchesAny(string $path, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($this->matches($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    public function matches(string $path, string $pattern): bool
    {
        $normalized = str_replace('\\', '/', $path);
        $regex = $this->patternToRegex($pattern);

        return preg_match($regex, $normalized) === 1;
    }

    /**
     * Converts a glob pattern to a PCRE pattern.
     *
     * Rules:
     *   `**`  → any number of characters (including slashes)
     *   `*`   → any number of non-slash characters
     *   `?`   → a single non-slash character
     *   other → literal
     */
    private function patternToRegex(string $pattern): string
    {
        $pattern = str_replace('\\', '/', $pattern);

        $out = '';
        $length = strlen($pattern);

        for ($i = 0; $i < $length; $i++) {
            $char = $pattern[$i];

            if ($char === '*') {
                if (($pattern[$i + 1] ?? '') === '*') {
                    $out .= '.*';
                    $i++;
                } else {
                    $out .= '[^/]*';
                }

                continue;
            }

            if ($char === '?') {
                $out .= '[^/]';

                continue;
            }

            if (in_array($char, ['.', '+', '(', ')', '[', ']', '{', '}', '|', '^', '$', '\\'], true)) {
                $out .= '\\'.$char;

                continue;
            }

            $out .= $char;
        }

        return '#^'.$out.'$#';
    }
}
