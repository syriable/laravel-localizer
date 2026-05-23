<?php

declare(strict_types=1);

namespace Syriable\Localizer\Analysis;

/**
 * Classifies a PHP expression (as it appears in source code) into one of
 * a handful of well-known shapes used to fill translation placeholders.
 *
 * The classifier is intentionally conservative: it does not parse a full
 * PHP AST. It uses a cascade of regular expressions matched against the
 * trimmed expression source. Anything that doesn't fit one of the simple
 * shapes is reported as {@see PlaceholderType::Expression} so the verbatim
 * source is preserved for downstream tooling.
 *
 * Classification cascade (first match wins):
 *
 *   1. static_method_call   ClassName::method(...)
 *   2. method_call          $obj->method(...)  ($obj->a->b->method(...) also matches)
 *   3. function_call        name(...)          (also fully-qualified \Ns\name(...))
 *   4. nested_object_prop   $obj->a->b         (no parens, 2+ path segments)
 *   5. object_property      $obj->prop         (no parens, 1 path segment)
 *   6. variable             $name
 *   7. literal              'foo', "foo", 42, 1.5, true, false, null
 *   8. expression           anything else (preserved verbatim)
 */
final class PhpExpressionClassifier
{
    private const IDENT = '[A-Za-z_][A-Za-z0-9_]*';

    private const QUALIFIED_NAME = '\\\\?(?:[A-Za-z_][A-Za-z0-9_]*\\\\)*[A-Za-z_][A-Za-z0-9_]*';

    /**
     * @return array{type: PlaceholderType, structure: array<string, mixed>}
     */
    public function classify(string $expression): array
    {
        $expr = trim($expression);

        if ($expr === '') {
            return $this->fallback($expression);
        }

        return $this->matchStaticMethod($expr)
            ?? $this->matchMethodCall($expr)
            ?? $this->matchFunctionCall($expr)
            ?? $this->matchNestedProperty($expr)
            ?? $this->matchObjectProperty($expr)
            ?? $this->matchVariable($expr)
            ?? $this->matchLiteral($expr)
            ?? $this->fallback($expr);
    }

    /**
     * @return array{type: PlaceholderType, structure: array<string, mixed>}|null
     */
    private function matchStaticMethod(string $expr): ?array
    {
        $pattern = '/^('.self::QUALIFIED_NAME.')::('.self::IDENT.')\s*\((.*)\)$/s';

        if (preg_match($pattern, $expr, $matches) !== 1) {
            return null;
        }

        if (! $this->parenthesesBalanced($matches[count($matches) - 1])) {
            return null;
        }

        return [
            'type' => PlaceholderType::StaticMethodCall,
            'structure' => [
                'class' => $matches[1],
                'method' => $matches[2],
                'arguments' => $this->splitArguments($matches[3]),
            ],
        ];
    }

    /**
     * @return array{type: PlaceholderType, structure: array<string, mixed>}|null
     */
    private function matchMethodCall(string $expr): ?array
    {
        $pattern = '/^(\$'.self::IDENT.')((?:->'.self::IDENT.')*?)->('.self::IDENT.')\s*\((.*)\)$/s';

        if (preg_match($pattern, $expr, $matches) !== 1) {
            return null;
        }

        if (! $this->parenthesesBalanced($matches[count($matches) - 1])) {
            return null;
        }

        $intermediate = $matches[2] === ''
            ? []
            : array_values(array_filter(explode('->', $matches[2])));

        return [
            'type' => PlaceholderType::MethodCall,
            'structure' => [
                'object' => $matches[1],
                'path' => $intermediate,
                'method' => $matches[3],
                'arguments' => $this->splitArguments($matches[4]),
            ],
        ];
    }

    /**
     * @return array{type: PlaceholderType, structure: array<string, mixed>}|null
     */
    private function matchFunctionCall(string $expr): ?array
    {
        $pattern = '/^('.self::QUALIFIED_NAME.')\s*\((.*)\)$/s';

        if (preg_match($pattern, $expr, $matches) !== 1) {
            return null;
        }

        if (! $this->parenthesesBalanced($matches[count($matches) - 1])) {
            return null;
        }

        return [
            'type' => PlaceholderType::FunctionCall,
            'structure' => [
                'function' => $matches[1],
                'arguments' => $this->splitArguments($matches[2]),
            ],
        ];
    }

    /**
     * @return array{type: PlaceholderType, structure: array<string, mixed>}|null
     */
    private function matchNestedProperty(string $expr): ?array
    {
        $pattern = '/^(\$'.self::IDENT.')((?:->'.self::IDENT.'){2,})$/';

        if (preg_match($pattern, $expr, $matches) !== 1) {
            return null;
        }

        $path = array_values(array_filter(explode('->', $matches[2])));

        return [
            'type' => PlaceholderType::NestedObjectProperty,
            'structure' => [
                'object' => $matches[1],
                'path' => $path,
            ],
        ];
    }

    /**
     * @return array{type: PlaceholderType, structure: array<string, mixed>}|null
     */
    private function matchObjectProperty(string $expr): ?array
    {
        $pattern = '/^(\$'.self::IDENT.')->('.self::IDENT.')$/';

        if (preg_match($pattern, $expr, $matches) !== 1) {
            return null;
        }

        return [
            'type' => PlaceholderType::ObjectProperty,
            'structure' => [
                'object' => $matches[1],
                'property' => $matches[2],
            ],
        ];
    }

    /**
     * @return array{type: PlaceholderType, structure: array<string, mixed>}|null
     */
    private function matchVariable(string $expr): ?array
    {
        if (preg_match('/^\$('.self::IDENT.')$/', $expr, $matches) !== 1) {
            return null;
        }

        return [
            'type' => PlaceholderType::Variable,
            'structure' => [
                'name' => $matches[0],
            ],
        ];
    }

    /**
     * @return array{type: PlaceholderType, structure: array<string, mixed>}|null
     */
    private function matchLiteral(string $expr): ?array
    {
        if (preg_match('/^-?\d+(?:\.\d+)?$/', $expr) === 1) {
            return [
                'type' => PlaceholderType::Literal,
                'structure' => ['value' => $expr],
            ];
        }

        if (in_array(strtolower($expr), ['true', 'false', 'null'], true)) {
            return [
                'type' => PlaceholderType::Literal,
                'structure' => ['value' => $expr],
            ];
        }

        $first = $expr[0];
        $last = $expr[strlen($expr) - 1];

        if (($first === "'" || $first === '"') && $first === $last && strlen($expr) >= 2) {
            return [
                'type' => PlaceholderType::Literal,
                'structure' => ['value' => $expr],
            ];
        }

        return null;
    }

    /**
     * @return array{type: PlaceholderType, structure: array<string, mixed>}
     */
    private function fallback(string $expr): array
    {
        return [
            'type' => PlaceholderType::Expression,
            'structure' => ['raw' => $expr],
        ];
    }

    /**
     * Splits a parenthesised argument list (without the outer parens) on
     * commas at depth zero, respecting nested brackets and string literals.
     *
     * @return list<string>
     */
    private function splitArguments(string $args): array
    {
        $args = trim($args);

        if ($args === '') {
            return [];
        }

        $result = [];
        $buffer = '';
        $depth = 0;
        $string = null;
        $escape = false;

        for ($i = 0, $n = strlen($args); $i < $n; $i++) {
            $ch = $args[$i];

            if ($escape) {
                $buffer .= $ch;
                $escape = false;

                continue;
            }

            if ($string !== null) {
                $buffer .= $ch;

                if ($ch === '\\') {
                    $escape = true;
                } elseif ($ch === $string) {
                    $string = null;
                }

                continue;
            }

            if ($ch === "'" || $ch === '"') {
                $buffer .= $ch;
                $string = $ch;

                continue;
            }

            if ($ch === '(' || $ch === '[' || $ch === '{') {
                $depth++;
                $buffer .= $ch;

                continue;
            }

            if ($ch === ')' || $ch === ']' || $ch === '}') {
                $depth--;
                $buffer .= $ch;

                continue;
            }

            if ($ch === ',' && $depth === 0) {
                $trimmed = trim($buffer);

                if ($trimmed !== '') {
                    $result[] = $trimmed;
                }

                $buffer = '';

                continue;
            }

            $buffer .= $ch;
        }

        $trimmed = trim($buffer);

        if ($trimmed !== '') {
            $result[] = $trimmed;
        }

        return $result;
    }

    /**
     * Verifies the expression's parentheses, brackets and braces are
     * balanced. Without this guard, a greedy regex match would accept
     * `getUser(), other()` as a single function call.
     */
    private function parenthesesBalanced(string $expr): bool
    {
        $depth = 0;
        $string = null;
        $escape = false;

        for ($i = 0, $n = strlen($expr); $i < $n; $i++) {
            $ch = $expr[$i];

            if ($escape) {
                $escape = false;

                continue;
            }

            if ($string !== null) {
                if ($ch === '\\') {
                    $escape = true;
                } elseif ($ch === $string) {
                    $string = null;
                }

                continue;
            }

            if ($ch === "'" || $ch === '"') {
                $string = $ch;

                continue;
            }

            if ($ch === '(' || $ch === '[' || $ch === '{') {
                $depth++;
            } elseif ($ch === ')' || $ch === ']' || $ch === '}') {
                $depth--;

                if ($depth < 0) {
                    return false;
                }
            }
        }

        return $depth === 0;
    }
}
