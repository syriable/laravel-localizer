# Beta Testing Guide (0.9.x)

Thank you for trying `syriable/laravel-localizer` during the beta period. Your real-world usage is what determines whether the API ships as-is for 1.0.0 or gets adjusted first. This guide walks you through verification, the questions we'd like answered, and how to report what you find.

## Installation against a real Laravel application

```bash
# In your existing Laravel 13 application:
composer require "syriable/laravel-localizer:^0.9.0" --dev

# Publish the config (optional — defaults are sensible):
php artisan vendor:publish --tag="localizer-config"

# First scan against your actual codebase:
php artisan localizer:scan --summary
```

If the summary shows zero strings, double-check `config/localizer.php` — the default `paths` covers `resources/views`, `resources/js`, and `app/`, but your project structure may differ.

## What to verify

A 15-minute checklist:

1. **Discovery.** Does `localizer:scan --summary` find roughly the number of strings you expected? If it found drastically fewer, the path or exclude config likely needs adjustment.

2. **Extractor coverage.** Watch the `skippedExtensions` hint at the bottom of `--summary` output. If a file type you care about appears there (e.g. `.antlers.html` for Statamic), you need to either add an extractor or register an existing one — and we want to know about that gap.

3. **Source locations.** Pick three random extracted strings, look up `$string->location->path:$string->location->line` in your editor, confirm the locations are accurate.

4. **Caching.** Run `localizer:scan --summary` twice in a row. The second run should report `Files from cache: N` ≈ `Files scanned: N` and complete in noticeably less time. If it doesn't, the cache isn't working.

5. **Cache survives `cache:clear`.** Run `php artisan cache:clear`, then `localizer:scan --summary` again. The cache should still be intact (`Files from cache` should be high). If `cache:clear` wiped the localizer cache, that's a bug.

6. **JSON output.** Run `localizer:scan --json > localizer-output.json`. Open the file. Confirm the JSON is valid and the shape is what you'd want to consume.

7. **Custom extractor.** If you have a non-standard file type, write a minimal `Syriable\Localizer\Contracts\Extractor` implementation and register it in `config/localizer.php`. Does the extension API feel right?

8. **Normalizer.** Add `Localizer::normalize(fn ($s) => str_starts_with($s->value, 'temp.') ? null : $s);` to a service provider. Confirm strings starting with `temp.` are dropped from the result.

## What we want to hear

When you file feedback, the most useful answers are:

- **What broke?** Stack traces, exact commands, exact config. Reproducer cases are gold.
- **What surprised you?** API shapes that didn't match your intuition. Names that felt wrong. Documentation gaps.
- **What was missing?** Features you reached for that aren't there. Workflows the engine doesn't support.
- **What was right?** Equally valuable — knowing what's working keeps us from "fixing" things that aren't broken.

We're explicitly NOT looking for opinions on the engine's single-responsibility scope (extraction only, no writing language files). That decision is locked. Everything else is in play.

## Where to file

- **Bugs:** [GitHub Issues](https://github.com/syriable/laravel-localizer/issues) with the `bug` label.
- **API feedback:** [GitHub Discussions](https://github.com/syriable/laravel-localizer/discussions) under "Ideas" or "Feedback".
- **Security:** Follow `SECURITY.md` — do not file publicly.

## Pinning your version

Pin to `^0.9.0` so patch releases (`0.9.1`, `0.9.2`, …) auto-upgrade safely but minor bumps (`0.10.0`) require an explicit decision:

```json
"require-dev": {
    "syriable/laravel-localizer": "^0.9.0"
}
```

If you want maximum stability during the beta, pin exactly (`"0.9.0"`) and upgrade deliberately.

## Beta timeline

The beta runs until we've had ~1 month of real-world usage AND no outstanding API feedback. At that point we'll tag `1.0.0` with the standard semver guarantees. Patch releases during the beta will be at least monthly; minor releases when warranted by feedback.

Thank you for helping us land a polished `1.0.0`.
