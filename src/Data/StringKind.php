<?php

declare(strict_types=1);

namespace Syriable\Localizer\Data;

/**
 * The two shapes of translatable strings Laravel recognises.
 *
 * A ShortKey looks like `pagination.next` and resolves against a PHP file
 * (e.g. `lang/en/pagination.php`). A JsonKey is free-form text (e.g.
 * "Welcome back") that resolves against `lang/en.json`.
 */
enum StringKind: string
{
    case ShortKey = 'short_key';
    case JsonKey = 'json_key';
}
