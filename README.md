<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="art/localizer-dark.svg">
    <source media="(prefers-color-scheme: light)" srcset="art/localizer-light.svg">
    <img alt="Syriable Localizer Logo" src="art/localizer-light.svg" width="600">
  </picture>
</p>

<p align="center">
<a href="https://packagist.org/packages/syriable/laravel-localizer"><img src="https://img.shields.io/packagist/v/syriable/laravel-localizer.svg?style=flat-square" alt="Latest Version on Packagist"></a>
<a href="https://github.com/syriable/laravel-localizer/actions?query=workflow%3Arun-tests+branch%3Amain"><img src="https://img.shields.io/github/actions/workflow/status/syriable/laravel-localizer/run-tests.yml?branch=main&label=tests&style=flat-square" alt="GitHub Tests Action Status"></a>
<a href="https://github.com/syriable/laravel-localizer/actions?query=workflow%3A%22Fix+PHP+code+style+issues%22+branch%3Amain"><img src="https://img.shields.io/github/actions/workflow/status/syriable/laravel-localizer/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square" alt="GitHub Code Style Action Status"></a>
<a href="https://github.com/syriable/laravel-localizer/actions?query=workflow%3Aphpstan+branch%3Amain"><img src="https://img.shields.io/github/actions/workflow/status/syriable/laravel-localizer/phpstan.yml?branch=main&label=phpstan&style=flat-square" alt="GitHub PHPStan Action Status"></a>
<a href="https://packagist.org/packages/syriable/laravel-localizer"><img src="https://img.shields.io/packagist/dt/syriable/laravel-localizer.svg?style=flat-square" alt="Total Downloads"></a>
</p>

> [!IMPORTANT]
> **This is a 0.9.x beta release.** The engine itself is production-quality (290 passing tests, PHPStan at level max, no risky warnings), and we're confident in the implementation. What we are NOT yet confident in is whether the **public API shape** is the right one — that's what the beta period is for.
>
> If you adopt 0.9.x, **pin to a specific minor version** (`"syriable/laravel-localizer": "^0.9.0"`) rather than `dev-main`. Patch releases (`0.9.1`, `0.9.2`, …) are safe to upgrade; minor releases (`0.10.0`, …) may introduce breaking changes during the beta period.
>
> Once the API stabilizes (target: ~1 month of beta with real-world usage), we'll tag `1.0.0` with the standard semver guarantees.
>
> **Trying the beta?** Read [BETA-TESTING.md](BETA-TESTING.md) for a 15-minute verification checklist and feedback channels. Bug reports and API feedback are very welcome via [GitHub Issues](https://github.com/syriable/laravel-localizer/issues).

---

**Syriable Localizer** is a focused, modern extraction engine for Laravel 13. It discovers translatable strings in your Blade, PHP, Vue, JavaScript, TypeScript, Livewire and Inertia source files, normalizes them, and hands them back as typed immutable DTOs through a stable, contracts-driven API.

It is deliberately scoped to a single responsibility: **extraction**. It does not write language files, manage translators, or sync with third parties — those concerns belong to ecosystem packages built on top of this engine.

```php
use Syriable\Localizer\Facades\Localizer;

$result = Localizer::scan();

foreach ($result->unique() as $string) {
    echo "[{$string->kind->value}] {$string->value}\n";
    echo "  at {$string->location->path}:{$string->location->line}\n";
}
```

## Where this fits

This package is the **extraction layer**: it gives you a clean, typed list of every translatable string in your codebase, with source locations and stable fingerprints. What you do with that list is up to you.

Common consumers of a `ScanResult`:

- **Generate language files.** For each short key, `$string->langFilePath($locale)` returns the full on-disk path (e.g. `lang/en/profile/buttons.php` or `lang/vendor/syriable/en/profile/buttons.php`); `$string->key` is the dotted path inside the file. For JSON keys, the same method returns `lang/{locale}.json`.
- **Detect missing translations.** Diff the unique strings against your existing language files to find untranslated keys.
- **Sync with translation vendors.** Push the unique strings to Lokalise, Crowdin, POEditor, or a custom translation backend; pull back the translations.
- **Audit translation coverage in CI.** Fail the build if scanned strings outnumber translated ones by more than X%.
- **Source-locate a key.** Given a key string, jump to `$location->path:$location->line` to find where it's used.

None of these are built into this package — they are downstream concerns that build on top of the engine. A companion writer/sync package is on the roadmap; in the meantime, the three [events](#extending-the-engine) plus the [`Localizer::normalize()`](#extending-the-engine) extension point give you everything needed to build your own.

## Support us

If this package helps you ship better Laravel applications, please consider [starring the repository](https://github.com/syriable/laravel-localizer) and following [@syriable](https://github.com/syriable) for updates on future ecosystem releases.

We highly appreciate hearing about how you're using it — open a Discussion to share, request a feature, or propose an extractor for a new source language.

## Installation

Install via composer. During the beta period, pin to `^0.9.0` so you'll receive patch updates but be insulated from breaking changes in `0.10.x`:

```bash
composer require "syriable/laravel-localizer:^0.9.0" --dev
```

The service provider is auto-discovered. Publish the config file with:

```bash
php artisan vendor:publish --tag="localizer-config"
```

This is the contents of the published config file:

```php
return [

    'paths' => [
        resource_path('views'),
        resource_path('js'),
        app_path(),
    ],

    'exclude' => [
        '**/node_modules/**',
        '**/vendor/**',
        '**/storage/**',
        '**/bootstrap/cache/**',
        '**/.git/**',
        '**/tests/**',
    ],

    'extractors' => [
        'blade'      => \Syriable\Localizer\Extractors\BladeExtractor::class,
        'livewire'   => \Syriable\Localizer\Extractors\LivewireExtractor::class,
        'inertia'    => \Syriable\Localizer\Extractors\InertiaExtractor::class,
        'php'        => \Syriable\Localizer\Extractors\PhpExtractor::class,
        'vue'        => \Syriable\Localizer\Extractors\VueExtractor::class,
        'typescript' => \Syriable\Localizer\Extractors\TypeScriptExtractor::class,
        'javascript' => \Syriable\Localizer\Extractors\JavaScriptExtractor::class,
    ],

    'cache' => [
        'enabled' => true,
        'path'    => storage_path('app/.localizer/cache.json'),
    ],

    'lock' => [
        'name'    => 'localizer:scan',
        'seconds' => 60,
    ],

];
```

**Extractor registration order matters.** When two extractors match the same file (e.g. `*.blade.php` matches both Blade and PHP), the first registered wins. The default order is correct for typical Laravel applications.

## Usage

### A simple scan

```php
use Syriable\Localizer\Facades\Localizer;

$result = Localizer::scan();
```

### An explicit request

```php
use Syriable\Localizer\Data\ScanRequest;

$result = Localizer::scan(new ScanRequest(
    paths: [resource_path('views'), resource_path('js')],
    exclude: ['**/node_modules/**', '**/legacy/**'],
    extractors: ['blade', 'vue'],
    useCache: true,
));
```

### The fluent builder

```php
$result = Localizer::in([resource_path('views'), resource_path('js')])
    ->exclude('**/legacy/**')
    ->only(['blade', 'vue'])
    ->fresh()
    ->scan();
```

The builder is immutable — every method returns a new instance, so configurations can be safely shared and reused.

### Working with the result

```php
$result->strings;                  // list<ExtractedString>, in discovery order
$result->count();                  // total strings including duplicates
$result->unique();                 // list<ExtractedString> deduplicated by fingerprint
$result->groupedByKind();          // ['short_key' => [...], 'json_key' => [...]]
$result->groupedByFile();          // ['pagination' => [...], 'buttons' => [...]]

$result->filesScanned;             // total files (cached + fresh)
$result->filesFromCache;           // files served from cache
$result->filesFresh();             // files extracted this run
$result->durationMs;               // float

$result->toArray();                // serializable representation
```

### The `ExtractedString` DTO

For ShortKeys, the value is decomposed into the filesystem target plus the key inside the target file:

```php
$string->value;         // 'Welcome back', 'pagination.next', or 'syriable::profile/buttons.submit.label'
$string->kind;          // StringKind::JsonKey | StringKind::ShortKey
$string->extractor;     // 'blade'

// ShortKey decomposition (all null/empty for JsonKey):
$string->package;       // 'syriable' (vendor namespace before `::`), or null
$string->directories;   // ['profile'] or ['profile', 'button', 'form'] — directory chain
$string->file;          // 'buttons' (the PHP file, without `.php`)
$string->key;           // 'submit.label' (the dotted path inside the file)

$string->location;      // SourceLocation { path, line, column }
$string->filePath();    // 'profile/buttons.php' — relative file path, or null for JsonKey
$string->langFilePath('en'); // 'lang/en/profile/buttons.php' or 'lang/vendor/syriable/en/...' — full on-disk path
$string->fingerprint(); // xxh128 content hash for deduplication
$string->toArray();
```

All DTOs are PHP 8.4 `readonly` — immutable by language guarantee, not by convention.

### Short keys vs JSON keys

Every extracted string is classified by **how it maps onto Laravel's translation file layout**, not by where it was extracted from. The classification determines which file the translation lives in and how the key resolves inside it.

**ShortKey** — a value that points at a PHP file and a key inside it. The general grammar:

```
[package::]dir1/dir2/.../file.key.subkey
```

with `package`, every `dir`, `file`, and every key segment being lowercase alphanumeric with `_` or `-`. Examples:

| Value                                    | package    | directories                     | file         | key               | Destination file                                    |
| ---------------------------------------- | ---------- | ------------------------------- | ------------ | ----------------- | --------------------------------------------------- |
| `pagination.next`                        | `null`     | `[]`                            | `pagination` | `next`            | `lang/{locale}/pagination.php`                      |
| `auth.failed.attempts`                   | `null`     | `[]`                            | `auth`       | `failed.attempts` | `lang/{locale}/auth.php`                            |
| `profile/buttons.submit.label`           | `null`     | `['profile']`                   | `buttons`    | `submit.label`    | `lang/{locale}/profile/buttons.php`                 |
| `profile/button/form/icon.submit.label`  | `null`     | `['profile', 'button', 'form']` | `icon`       | `submit.label`    | `lang/{locale}/profile/button/form/icon.php`        |
| `syriable::buttons.submit`               | `syriable` | `[]`                            | `buttons`    | `submit`          | `lang/vendor/syriable/{locale}/buttons.php`         |
| `syriable::profile/buttons.submit.label` | `syriable` | `['profile']`                   | `buttons`    | `submit.label`    | `lang/vendor/syriable/{locale}/profile/buttons.php` |

The key path resolves inside the file's returned array via `data_get($array, $key)`. For `submit.label` against `['submit' => ['label' => 'Save']]`, the value is `'Save'`.

A string is classified as ShortKey only when it strictly matches the grammar above. Any whitespace, mixed case, punctuation, or malformed structure (`/buttons.submit`, `profile//x.y`, `::buttons.x`) falls through to **JsonKey** — in which case the entire string IS the key and the translation lives in `lang/{locale}.json`:

| Value                      | Destination file     |
| -------------------------- | -------------------- |
| `Welcome back`             | `lang/{locale}.json` |
| `You have :count messages` | `lang/{locale}.json` |
| `Click here to continue`   | `lang/{locale}.json` |

The name "JsonKey" refers to Laravel's terminology for translations whose **keys are stored in a JSON file** — it has nothing to do with whether the string was extracted from a `.json` source file (the package doesn't scan those). All JsonKeys go into the single `lang/{locale}.json` file.

### The Artisan command

A single command. Every option is explicit and predictable.

```bash
# Use config defaults
php artisan localizer:scan

# Specific paths
php artisan localizer:scan resources/views resources/js

# Ignore the cache
php artisan localizer:scan --fresh

# Limit to specific extractors
php artisan localizer:scan --extractor=blade --extractor=vue

# JSON output (for piping into other tools)
php artisan localizer:scan --json

# Summary only
php artisan localizer:scan --summary
```

The command always exits with `0` on a successful scan — an empty result is not an error.

### Caching and incremental scans

Every scan computes an [xxh128](https://github.com/Cyan4973/xxHash) content fingerprint for each discovered file. The cache (a single JSON document at `storage/app/.localizer/cache.json`) maps each file path to its last-known fingerprint and the strings extracted at that time. On the next scan:

- File fingerprint matches → strings loaded from cache (no re-read, no re-extraction).
- File fingerprint differs → file re-extracted, cache updated.

A no-change scan over thousands of files completes in tens of milliseconds. The cache is written atomically (`rename()` over a temporary file) and protected by Laravel's lock provider so parallel CI runs do not corrupt each other.

**Cache versioning.** The cache file embeds a schema version. When you upgrade between major versions of this package, the format may change incompatibly and the old cache will be rejected. If you see a `LocalizerException` mentioning a cache-version mismatch after upgrading, run the scan once with `--fresh` to rebuild it:

```bash
php artisan localizer:scan --fresh
```

This is a one-time cost per major upgrade. Minor and patch versions preserve cache compatibility.

### Extending the engine

The engine has three extension points. All of them follow standard Laravel conventions — no engine subclassing required.

**1. Custom extractors.** Implement `Syriable\Localizer\Contracts\Extractor` and register the class in `config('localizer.extractors')`:

```php
use Syriable\Localizer\Contracts\Extractor;
use Syriable\Localizer\Data\DiscoveredFile;

final class TwigExtractor implements Extractor
{
    public function name(): string
    {
        return 'twig';
    }

    public function patterns(): array
    {
        return ['*.twig'];
    }

    public function extract(DiscoveredFile $file, string $contents): iterable
    {
        // yield ExtractedString instances...
    }
}
```

**2. Normalizers.** Transform or drop strings before they reach the result. A normalizer is any callable or `Normalizer` implementation that returns one of three values:

- **The original `ExtractedString`** → the string passes through unchanged.
- **A new `ExtractedString`** → the original is replaced. Use this to rewrite values, change kinds, attach groups, etc.
- **`null`** → the string is dropped from the result entirely.

Normalizers run in registration order and apply to every scan. They must be pure functions of their input — no I/O, no shared mutable state.

```php
// Drop strings: filter out debug keys.
Localizer::normalize(fn ($string) => str_starts_with($string->value, 'debug.') ? null : $string);

// Transform strings: trim whitespace from values.
Localizer::normalize(fn ($string) => $string->withValue(trim($string->value)));

// Filter + transform in one normalizer: drop empties, lowercase the rest.
Localizer::normalize(function ($string) {
    $trimmed = trim($string->value);

    return $trimmed === '' ? null : $string->withValue(strtolower($trimmed));
});
```

Clear all registered normalizers with `Localizer::withoutNormalizers()` — useful in tests or when temporarily disabling a downstream package's normalizers.

**3. Events.** For everything else — telemetry, progress bars, downstream packages — listen to one of the three events:

| Event           | Fired                               | Payload                                                      |
| --------------- | ----------------------------------- | ------------------------------------------------------------ |
| `ScanStarted`   | Before discovery                    | `ScanRequest`                                                |
| `FileExtracted` | Once per file, including cache hits | `DiscoveredFile`, `list<ExtractedString>`, `bool $fromCache` |
| `ScanCompleted` | After normalization, before return  | `ScanResult`                                                 |

```php
use Syriable\Localizer\Events\ScanCompleted;
use Illuminate\Support\Facades\Event;

Event::listen(ScanCompleted::class, function (ScanCompleted $event) {
    info("Scan complete: {$event->result->count()} strings");
});
```

## Testing

```bash
composer test
```

This runs the full Pest suite. Other quality gates:

```bash
composer test-coverage    # Pest with coverage, min 95%
composer analyse          # Larastan at level max
composer format           # Apply Pint
composer format-check     # Verify formatting without changing files
composer check            # format-check + analyse + test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Syriable](https://github.com/syriable)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
