<?php

declare(strict_types=1);

namespace Syriable\Localizer\Support;

use Syriable\Localizer\Data\StringKind;

/**
 * Classifies an extracted raw string as a ShortKey or JsonKey, and
 * decomposes short keys into their target translation-file location.
 *
 * The mental model is not "Laravel translation key grammar" but rather
 * "filesystem target for the translation": every short key names a
 * specific PHP file under `lang/{locale}/` (or `lang/vendor/{package}/
 * {locale}/`) and a specific key path inside that file.
 *
 * The general grammar is:
 *
 *   [package::]dir1/dir2/.../file.key.subkey...
 *
 * Where:
 *
 *   - `package`     (optional) — vendor identifier; maps to
 *                                `lang/vendor/{package}/`.
 *   - `dir1/dir2/…` (optional) — subdirectory chain INSIDE the locale
 *                                directory. May be empty.
 *   - `file`        (required) — name of the PHP file (without `.php`).
 *   - `key.subkey`  (required) — dotted path inside the PHP file's
 *                                returned array.
 *
 * Every token between separators must be lowercase alphanumeric with
 * `_` or `-` only — no whitespace, no mixed case, no punctuation.
 *
 * Examples
 * --------
 *
 * "pagination.next"
 *   package      = null
 *   directories  = []
 *   file         = "pagination"
 *   key          = "next"
 *   destination  = lang/{locale}/pagination.php → ['next' => ...]
 *
 * "auth.failed.attempts"
 *   package      = null
 *   directories  = []
 *   file         = "auth"
 *   key          = "failed.attempts"
 *   destination  = lang/{locale}/auth.php → ['failed' => ['attempts' => ...]]
 *
 * "profile/button/form/icon.submit.label"
 *   package      = null
 *   directories  = ["profile", "button", "form"]
 *   file         = "icon"
 *   key          = "submit.label"
 *   destination  = lang/{locale}/profile/button/form/icon.php
 *                  → ['submit' => ['label' => ...]]
 *
 * "syriable::profile/buttons.submit.label"
 *   package      = "syriable"
 *   directories  = ["profile"]
 *   file         = "buttons"
 *   key          = "submit.label"
 *   destination  = lang/vendor/syriable/{locale}/profile/buttons.php
 *                  → ['submit' => ['label' => ...]]
 *
 * "Welcome back"
 *   JsonKey (whitespace in segments)
 *   destination  = lang/{locale}.json → 'Welcome back'
 */
final class StringClassifier
{
    /**
     * One segment: lowercase alphanumeric plus `_` and `-`.
     */
    private const TOKEN = '[a-z0-9_-]+';

    /**
     * Captures:
     *   $1 — package (without `::`), or empty if absent
     *   $2 — directory chain ending with `/`, or empty if absent
     *   $3 — file name (no `.php`)
     *   $4 — key path with leading `.`
     */
    private const SHORT_KEY_PATTERN =
        '/^(?:('.self::TOKEN.')::)?((?:'.self::TOKEN.'\/)*)('.self::TOKEN.')((?:\.'.self::TOKEN.')+)$/';

    /**
     * Returns the kind for the given raw string.
     */
    public function classify(string $value): StringKind
    {
        return preg_match(self::SHORT_KEY_PATTERN, $value) === 1
            ? StringKind::ShortKey
            : StringKind::JsonKey;
    }

    /**
     * Returns the package name (the segment before `::`) or null.
     *
     *   "pagination.next"                            null
     *   "syriable::profile/buttons.submit.label"     "syriable"
     *   "profile/buttons.submit"                     null
     */
    public function packageFor(string $value): ?string
    {
        if (! $this->matches($value, $matches)) {
            return null;
        }

        return ($matches[1] ?? '') === '' ? null : $matches[1];
    }

    /**
     * Returns the directory chain INSIDE the locale directory.
     *
     * For plain keys this is an empty list. For namespaced or nested
     * keys it is the slash-separated path between the package (or the
     * start) and the file name.
     *
     *   "pagination.next"                            []
     *   "profile/buttons.submit"                     ["profile"]
     *   "profile/button/form/icon.submit.label"      ["profile", "button", "form"]
     *   "syriable::profile/buttons.submit.label"     ["profile"]
     *
     * @return list<string>
     */
    public function directoriesFor(string $value): array
    {
        if (! $this->matches($value, $matches)) {
            return [];
        }

        $raw = rtrim($matches[2] ?? '', '/');

        return $raw === '' ? [] : explode('/', $raw);
    }

    /**
     * Returns the file name (without `.php`) — i.e. the segment between
     * the last `/` (if any) and the first `.`. Returns null for JSON
     * keys.
     *
     *   "pagination.next"                            "pagination"
     *   "profile/button/form/icon.submit.label"      "icon"
     *   "syriable::profile/buttons.submit.label"     "buttons"
     */
    public function fileFor(string $value): ?string
    {
        if (! $this->matches($value, $matches)) {
            return null;
        }

        return $matches[3] ?? null;
    }

    /**
     * Returns the dotted key path INSIDE the PHP file's returned array.
     * Returns null for JSON keys.
     *
     *   "pagination.next"                            "next"
     *   "auth.failed.attempts"                       "failed.attempts"
     *   "profile/button/form/icon.submit.label"      "submit.label"
     *   "syriable::profile/buttons.submit.label"     "submit.label"
     */
    public function keyFor(string $value): ?string
    {
        if (! $this->matches($value, $matches)) {
            return null;
        }

        return isset($matches[4]) ? ltrim($matches[4], '.') : null;
    }

    /**
     * @param array<int, string>|null $matches
     */
    private function matches(string $value, ?array &$matches = null): bool
    {
        return preg_match(self::SHORT_KEY_PATTERN, $value, $matches) === 1;
    }
}
