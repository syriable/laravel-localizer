<?php

declare(strict_types=1);

namespace Syriable\Localizer\Analysis;

/**
 * Classification of a PHP expression used as a translation placeholder value.
 *
 * Each variant maps to a specific structural shape returned by
 * {@see PhpExpressionClassifier::classify()}. Downstream consumers (such as
 * the AI translation strategy) use the type to decide how to incorporate
 * the placeholder's source into the translation prompt.
 */
enum PlaceholderType: string
{
    /** A bare variable: `$name`. */
    case Variable = 'variable';

    /** A single-level property access: `$user->name`. */
    case ObjectProperty = 'object_property';

    /** A multi-level property access: `$user->profile->full_name`. */
    case NestedObjectProperty = 'nested_object_property';

    /** A free function call: `getUser()`, `config('app.name')`. */
    case FunctionCall = 'function_call';

    /** A method call on an instance: `$user->getOrders()`. */
    case MethodCall = 'method_call';

    /** A static method call: `User::find($id)`, `Auth::user()`. */
    case StaticMethodCall = 'static_method_call';

    /** A string or numeric literal: `'admin'`, `42`, `1.5`. */
    case Literal = 'literal';

    /** Any other PHP expression we couldn't classify more specifically. */
    case Expression = 'expression';
}
