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

Install via Composer:

```bash
composer require syriable/laravel-localizer --dev
```

**Requirements:** PHP 8.4+, Laravel 13.x.

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

## Analyzing translation calls

Beyond simple key extraction, the package can perform a deeper inspection
of every translation call site — capturing not just the key, but the
**replacements array** and the kind of PHP expression behind each
placeholder.

```bash
php artisan localizer:analyze                       # configured paths
php artisan localizer:analyze app/ resources/views  # explicit paths
php artisan localizer:analyze --json                # structured output
php artisan localizer:analyze --key=auth.failed     # filter by key
```

For every `__()`, `trans()`, `@lang()`, `trans_choice()`, `Lang::get()`
or `Lang::choice()` call the analyzer extracts the literal key plus
every `:placeholder => $expression` pair, then classifies each
expression into one of eight typed categories: `variable`,
`object_property`, `nested_object_property`, `function_call`,
`method_call`, `static_method_call`, `literal`, `expression`.

Example: for `__('welcome', ['name' => $user->profile->full_name, 'count' => getOrdersCount($user->id)])` the JSON output contains:

```json
{
    "key": "welcome",
    "function": "__",
    "placeholders": [
        {
            "placeholder": ":name",
            "source": "$user->profile->full_name",
            "type": "nested_object_property",
            "structure": { "object": "$user", "path": ["profile", "full_name"] }
        },
        {
            "placeholder": ":count",
            "source": "getOrdersCount($user->id)",
            "type": "function_call",
            "structure": { "function": "getOrdersCount", "arguments": ["$user->id"] }
        }
    ],
    "lang_example": { "welcome": "Welcome :name :count" }
}
```

This is the engine behind the AI generation strategy's prompt-enrichment
step, and it is exposed standalone so downstream tooling (linters,
audits, IDE plugins) can reason about placeholder shapes.

## Generating translation files

The package ships a companion `localizer:generate` command that turns
a scan result into actual PHP translation files. It's an optional
convenience built on top of the extraction engine — the engine itself
remains write-free.

```bash
php artisan localizer:generate                 # uses app.locale
php artisan localizer:generate --locale=fr
php artisan localizer:generate --all-locales   # from localizer.generator.locales
php artisan localizer:generate --dry-run       # preview without writing
php artisan localizer:generate --strategy=key  # humanized | key | empty | ai
php artisan localizer:generate --namespace=acme
php artisan localizer:generate --force         # overwrite existing values
php artisan localizer:generate --fresh         # ignore scan cache
php artisan localizer:generate --no-analyze    # skip call-site analysis
```

Before generating, the command automatically runs the call-site analyzer
over your source paths and passes the resulting placeholder context to
any strategy that implements `AnalysisAwareStrategy` (most notably the
`ai` strategy). Use `--no-analyze` to disable this step.

For every scanned ShortKey, the generator writes the missing keys to
the corresponding PHP file under `lang/{locale}/` (or
`lang/vendor/{package}/{locale}/` for vendor-namespaced keys), preserving
the directory structure encoded in the key. JsonKey strings are written
to `lang/{locale}.json`. A key like `profile/btn/form.submit.label`
produces:

```php
// lang/en/profile/btn/form.php
<?php

declare(strict_types=1);

return [
    'submit' => [
        'label' => 'Label',
    ],
];
```

**Safety guarantees:**

- Existing translation values are **never overwritten** unless you pass
  `--force`. Re-running the command on a populated `lang/` directory
  only adds the missing keys.
- All writes are atomic (rename-over-temp via `AtomicWriter`).
- `--dry-run` never touches the filesystem; it prints what would be
  written.
- Locales are validated against `[A-Za-z0-9_-]+` to prevent
  path-traversal injection through crafted locale strings.

**Value strategies:**

| Strategy              | Example key         | Generated value                                |
| --------------------- | ------------------- | ---------------------------------------------- |
| `humanized` (default) | `submit_btn`        | `Submit btn`                                   |
| `key`                 | `auth.login.failed` | `auth.login.failed`                            |
| `empty`               | any                 | `''`                                           |
| `ai`                  | `auth.login.failed` | `Connexion échouée` (real translation via API) |

Register a custom strategy from a service provider:

```php
use Syriable\Localizer\Generator\StrategyRegistry;

$this->app->extend(StrategyRegistry::class, function (StrategyRegistry $registry) {
    $registry->register(new MyCustomStrategy);
    return $registry;
});
```

Custom strategies may also implement
`Syriable\Localizer\Contracts\LocaleAwareStrategy` (to receive the
target locale before generation begins) or
`Syriable\Localizer\Contracts\AnalysisAwareStrategy` (to receive the
key → `TranslationCallAnalysis` map produced by the analyzer).

**Configuring default locales:**

```php
// config/localizer.php
'generator' => [
    'locales'  => ['en', 'fr', 'de'],  // for --all-locales
    'strategy' => 'humanized',          // default --strategy
],
```

### AI translation strategy

The `ai` strategy translates missing keys via the Anthropic Messages
API, with persistent caching and placeholder preservation. It is
opt-in and never invoked unless you pass `--strategy=ai` (or set it as
the default).

```bash
export ANTHROPIC_API_KEY="sk-ant-..."

php artisan localizer:generate --locale=fr --strategy=ai
php artisan localizer:generate --locale=de --strategy=ai --dry-run
```

For each missing key the strategy:

1. **Masks** any `:placeholder` tokens (e.g. `:name`, `:count`) to
   opaque `{{P0}}`, `{{P1}}` tokens so the model cannot accidentally
   translate them.
2. **Checks** the persistent cache (keyed by model + source locale +
   target locale + masked text). Cache hits skip the API call.
3. **Calls** the Anthropic API with a prompt enriched by call-site
   analysis — the AI sees which placeholder is a person's name, which
   is a count, etc., and can produce grammatically appropriate
   translations.
4. **Restores** the original `:placeholder` tokens in the translated
   result.
5. **Falls back** to the configured fallback strategy (defaults to
   `humanized`) on any API or network failure — generation never
   crashes mid-run.

The cache lives at `storage/app/.localizer/ai-cache.json` by default
and is deliberately kept outside `storage/framework/cache/` so that
`php artisan cache:clear` never evicts expensive AI translations.

```php
// config/localizer.php
'ai' => [
    'model'             => env('LOCALIZER_AI_MODEL', 'claude-opus-4-7'),
    'api_key'           => env('ANTHROPIC_API_KEY', ''),
    'source_locale'     => env('LOCALIZER_AI_SOURCE_LOCALE', 'en'),
    'fallback_strategy' => 'humanized',
    'cache_path'        => storage_path('app/.localizer/ai-cache.json'),
],
```

Changing the model, source locale, or target locale automatically
invalidates the cache for affected entries — the key is a content
hash of all four parameters.

## Testing

```bash
composer test
```

This runs the full Pest suite. Other quality gates:

```bash
composer test-coverage    # Pest with coverage, min 95%
composer analyse          # Larastan at level 8
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
