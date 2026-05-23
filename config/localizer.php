<?php

declare(strict_types=1);
use Syriable\Localizer\Extractors\BladeExtractor;
use Syriable\Localizer\Extractors\InertiaExtractor;
use Syriable\Localizer\Extractors\JavaScriptExtractor;
use Syriable\Localizer\Extractors\LivewireExtractor;
use Syriable\Localizer\Extractors\PhpExtractor;
use Syriable\Localizer\Extractors\TypeScriptExtractor;
use Syriable\Localizer\Extractors\VueExtractor;

return [

    /*
    |--------------------------------------------------------------------------
    | Paths
    |--------------------------------------------------------------------------
    |
    | Absolute or base-relative paths that will be scanned for translatable
    | strings. Each path is walked recursively. Non-existing paths are
    | silently skipped — this keeps the engine resilient in fresh projects.
    |
    */
    'paths' => [
        resource_path('views'),
        resource_path('js'),
        app_path(),
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded Patterns
    |--------------------------------------------------------------------------
    |
    | Glob-style patterns matched against the path of every discovered file
    | (relative to the scan root). Any match excludes the file. These are
    | applied AFTER the path walk but BEFORE extractor resolution.
    |
    */
    'exclude' => [
        '**/node_modules/**',
        '**/vendor/**',
        '**/storage/**',
        '**/bootstrap/cache/**',
        '**/.git/**',
        '**/tests/**',
    ],

    /*
    |--------------------------------------------------------------------------
    | Extractors
    |--------------------------------------------------------------------------
    |
    | The extractors that will be registered with the engine. Each maps a
    | logical name to its implementation. Order is preserved. To disable
    | an extractor, remove its entry. To add a custom extractor, append
    | the fully-qualified class name.
    |
    | Every class listed here MUST implement
    | \Syriable\Localizer\Contracts\Extractor.
    |
    */
    'extractors' => [
        // Order matters: more specific patterns must register first.
        // BladeExtractor handles *.blade.php before PhpExtractor sees *.php.
        // LivewireExtractor handles *Livewire*.php before PhpExtractor.
        // InertiaExtractor handles *Page.vue / *Page.tsx before Vue/TypeScript.
        'blade' => BladeExtractor::class,
        'livewire' => LivewireExtractor::class,
        'inertia' => InertiaExtractor::class,
        'php' => PhpExtractor::class,
        'vue' => VueExtractor::class,
        'typescript' => TypeScriptExtractor::class,
        'javascript' => JavaScriptExtractor::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Controls the per-file fingerprint cache used to make repeated scans
    | near-instant. Set `enabled` to false to disable caching entirely.
    | The `path` is the absolute location of the cache file on disk.
    |
    | Default location: storage/app/.localizer/cache.json
    |
    | This path is DELIBERATELY outside storage/framework/cache/ so that
    | running `php artisan cache:clear` does not wipe the localizer cache.
    | Incremental scans should survive routine cache flushes — the
    | localizer cache is content-addressed by xxh128 fingerprints, so
    | stale entries are auto-invalidated when source files actually
    | change, not when the framework cache happens to be cleared.
    |
    */
    'cache' => [
        'enabled' => true,
        'path' => storage_path('app/.localizer/cache.json'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Concurrency Lock
    |--------------------------------------------------------------------------
    |
    | When multiple processes invoke a scan concurrently (e.g. parallel CI
    | jobs sharing a cache file), the engine acquires this lock to serialize
    | cache writes. The lock is held only for the duration of a scan.
    |
    */
    'lock' => [
        'name' => 'localizer:scan',
        'seconds' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Generator
    |--------------------------------------------------------------------------
    |
    | Configuration for `php artisan translations:generate`.
    |
    | `locales`   — Locales written when `--all-locales` is passed. Defaults
    |               to the application's own `app.locale` when empty.
    |
    | `strategy`  — Default value-generation strategy used when `--strategy`
    |               is not supplied on the command line.
    |               Built-in values: "humanized", "key", "empty".
    |               Register custom strategies via StrategyRegistry.
    |
    */
    'generator' => [
        'locales' => [],
        'strategy' => 'humanized',
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Translation
    |--------------------------------------------------------------------------
    |
    | Configuration for the `ai` generation strategy, which uses the
    | Anthropic Messages API to translate missing keys into the target locale.
    |
    | `model`             — Anthropic model ID. Defaults to claude-opus-4-7.
    | `api_key`           — Anthropic API key. Reads ANTHROPIC_API_KEY by default.
    | `source_locale`     — The locale your source strings are already in.
    |                       Defaults to LOCALIZER_AI_SOURCE_LOCALE or "en".
    | `fallback_strategy` — Strategy name used when the API call fails.
    | `cache_path`        — Absolute path to the AI translation cache file.
    |                       Kept separate from the scan cache so that
    |                       `php artisan cache:clear` never evicts expensive
    |                       AI translations.
    |
    */
    'ai' => [
        'model' => env('LOCALIZER_AI_MODEL', 'claude-opus-4-7'),
        'api_key' => env('ANTHROPIC_API_KEY', ''),
        'source_locale' => env('LOCALIZER_AI_SOURCE_LOCALE', 'en'),
        'fallback_strategy' => 'humanized',
        'cache_path' => storage_path('app/.localizer/ai-cache.json'),
    ],

];
