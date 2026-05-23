# Changelog

All notable changes to `syriable/laravel-localizer` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Automatic translation file generator.** A new `php artisan
  translations:generate` command reads the scan result and writes (or
  previews) PHP translation files with placeholder values for any
  missing keys. Includes:
  - Three built-in value strategies: `humanized` (default, "submit_btn"
    → "Submit btn"), `key` (raw dot-notation), and `empty` (blank
    placeholder). Custom strategies can be registered via
    `Generator\StrategyRegistry`.
  - Recursive merge that preserves existing translations unless
    `--force` is passed. Existing values are never silently overwritten.
  - Nested PHP array output: `profile/btn/form.submit.label` becomes
    `lang/{locale}/profile/btn/form.php` with structure
    `['submit' => ['label' => ...]]`.
  - Vendor namespace support: `acme::buttons.submit` writes to
    `lang/vendor/acme/{locale}/buttons.php`.
  - Atomic writes via the existing `AtomicWriter` (rename-over-temp).
  - Command options: `--locale`, `--all-locales` (from
    `localizer.generator.locales`), `--dry-run`, `--force`,
    `--namespace`, `--strategy`, `--fresh`.
- `Generator\TranslationArrayBuilder` — builds nested PHP arrays from
  flat dot-notation keys.
- `Generator\TranslationMergeService` — recursive merge with optional
  force-overwrite, plus `countNew()` / `countAll()` for diagnostics.
- `Generator\TranslationPhpRenderer` — renders nested arrays to
  `declare(strict_types=1)` PHP files with four-space indentation.
- `Generator\TranslationFileRepository` — read (via `include`), write
  (via `AtomicWriter`), and preview translation files.
- `Generator\TranslationFileGenerator` — per-file orchestration: build
  → read existing → count new → merge → write or preview.
- `Generator\TranslationGenerationPipeline` — top-level orchestrator
  grouping strings by `langFilePath(locale)` with namespace filtering.
- `Contracts\GenerationStrategy` — interface for pluggable value
  strategies.
- `Data\GenerationRequest` / `Data\GenerationResult` DTOs.
- `localizer.generator.{locales,strategy}` config keys.
- `LocalizerException::unknownStrategy()` for misconfigured strategies.

## [v1.0.0](https://github.com/syriable/laravel-localizer/releases/tag/v1.0.0/compare/v1.0.0...v1.0.0) - 2026-05-22

### What's Changed

* fix: CallExtractor false positives from .t() / .tc() method calls by @alkhatibsy in https://github.com/syriable/laravel-localizer/pull/1
* fix: normalise DiscoveredFile relativePath to forward slashes (Windows CI) by @alkhatibsy in https://github.com/syriable/laravel-localizer/pull/10
* fix: remove invalid usePage().props.translations from InertiaExtractor by @alkhatibsy in https://github.com/syriable/laravel-localizer/pull/2
* fix: wrap LockTimeoutException inside LocalizerException by @alkhatibsy in https://github.com/syriable/laravel-localizer/pull/3
* fix: dispatch ScanStarted after lock acquisition, not before by @alkhatibsy in https://github.com/syriable/laravel-localizer/pull/4
* feat: add ScanResult::groupedByFilePath() to fix directory collision by @alkhatibsy in https://github.com/syriable/laravel-localizer/pull/5
* fix: prune stale FileScanCache entries for deleted files by @alkhatibsy in https://github.com/syriable/laravel-localizer/pull/6
* perf: cache compiled regex patterns in CallExtractor by @alkhatibsy in https://github.com/syriable/laravel-localizer/pull/7
* fix: add *Component.php pattern to LivewireExtractor for Livewire 3 by @alkhatibsy in https://github.com/syriable/laravel-localizer/pull/8
* fix: raise PHPStan to level 8 and correct README claim by @alkhatibsy in https://github.com/syriable/laravel-localizer/pull/9
* release: 1.0.0 — production stabilization by @alkhatibsy in https://github.com/syriable/laravel-localizer/pull/11

### New Contributors

* @alkhatibsy made their first contribution in https://github.com/syriable/laravel-localizer/pull/1

**Full Changelog**: https://github.com/syriable/laravel-localizer/commits/v1.0.0

## [1.0.0](https://github.com/syriable/laravel-localizer/releases/tag/v1.0.0) - 2026-05-22

First stable release. The public API — `Localizer`, `PendingScan`, every
`Data\*` DTO, every `Contracts\*` interface, the `localizer:scan` command,
the `localizer.*` config keys, and the on-disk cache schema — is now
covered by semver. Patch releases are bug fixes only; minor releases add
backwards-compatible features; major releases may break the API.

### Added

- **Translation tree decomposition on `ExtractedString`.** ShortKeys now
  expose `package`, `directories`, `file`, and `key` — a four-field
  decomposition that mirrors Laravel's on-disk language-file layout.
  `filePath()` returns the relative path to the target PHP file and
  `langFilePath($locale)` returns the full on-disk path including the
  `lang/` prefix and the `vendor/{package}/` segment when applicable.
  Locales are validated against a safe character set to prevent
  path-traversal injection.
- `ScanResult::groupedByFilePath()` keys ShortKey buckets on the full
  relative path (e.g. `profile/buttons.php`), avoiding the directory
  collision that the basename-keyed `groupedByFile()` exhibits.
- `ScanCache::prune(array $knownPaths)` evicts cache entries for files
  that no longer exist on disk, keeping the cache file from growing
  unbounded. `PersistCache` invokes it automatically at the end of each
  scan.
- `LocalizerException::lockTimeout(int $seconds, Throwable $cause)`
  wraps Laravel's `LockTimeoutException` so callers only need to catch
  the package's own exception type.
- `StringClassifier` exposes `packageFor()`, `directoriesFor()`,
  `fileFor()`, and `keyFor()` for downstream consumers that want to
  decompose raw key strings without constructing an `ExtractedString`.
- `LivewireExtractor` matches `*Component.php` in addition to
  `*Livewire*.php`, covering the common Livewire 3 suffix convention.
- Diagnostic fields on `ScanResult`: `skippedExtensions` counts files
  discovered but skipped because no extractor matched, surfaced in both
  the summary output and `--json` output of the Artisan command.

### Changed

- **BREAKING (vs 0.9.0):** The `group` and `namespace` fields on
  `ExtractedString` were replaced by the four-field tree decomposition
  (`package`, `directories`, `file`, `key`). Downstream code that wrote
  language files must update — see the migration notes below.
- **BREAKING (vs 0.9.0):** `ScanResult::groupedByShortKeyGroup()`
  renamed to `groupedByFile()`. Behaviour is unchanged.
- **BREAKING (vs 0.9.0):** The on-disk cache schema is bumped from v1
  to v2. Caches written by 0.9.0 are automatically treated as empty on
  first scan under 1.0.0 — every file is re-extracted under the new
  decomposition. No manual `--fresh` is required.
- `ExtractedString::fingerprint()` now incorporates the full
  decomposition (package, directories, file, value), so values
  targeting different translation files are never deduplicated together.
- `ScanCommand` table output replaces the "Group" column with separate
  "Package" and "File" columns.
- `DiscoveredFile::$relativePath` is always forward-slash separated,
  regardless of host OS. Fixes a Windows-specific test failure and
  ensures exclusion globs behave identically on every platform.
- PHPStan is now configured at level 8 (the highest level that the code
  passes cleanly under larastan + strict + deprecation rules).

### Fixed

- `CallExtractor` no longer matches method calls like `this.t('x')` or
  `router.t('y')` as translation invocations. The negative lookbehind
  now excludes `.` in addition to identifier characters.
- `InertiaExtractor::FUNCTIONS` no longer lists
  `usePage().props.translations`, which is a property accessor and not
  a callable.
- `ScanPipeline` dispatches `ScanStarted` *after* acquiring the lock
  (not before), so listeners receive the event at the moment the scan
  actually begins — not up to `lockSeconds` earlier.
- `ScanPipeline` catches `Illuminate\Contracts\Cache\LockTimeoutException`
  and re-throws as `LocalizerException::lockTimeout()` so callers only
  need to import the package's own exception namespace.
- `LocalizerException::lockTimeout` message now references the correct
  config key (`localizer.lock.seconds`, not `localizer.lock_seconds`).
- `DiscoverFiles::relativise()` is hardened with a separator-boundary
  check so overlapping scan roots (`/foo/bar` and `/foo/barbaz`) cannot
  produce an incorrect relative path.
- `StringClassifier::fileFor()` and `keyFor()` defensively guard
  against undefined regex capture indices — required for PHPStan level 8
  cleanliness.

### Performance

- `CallExtractor` caches compiled regex patterns per function-name list,
  amortising the `preg_quote` + `implode` cost across all files in a
  scan to a single build per extractor.

### Removed

- **BREAKING (vs 0.9.0):** The `Syriable\Localizer\Contracts\ResultStore`
  interface was unused by the engine and is removed. Downstream
  consumers that wrote against it should reach for the
  `Events\ScanCompleted` event or the `ScanResult` returned by
  `Localizer::scan()` instead.

### Migration from 0.9.x

If you adopted the 0.9.x beta, the upgrade requires two code-level
changes:

```php
// 0.9.0 — group + namespace
$file = $string->group ?? '';
$namespace = $string->namespace;  // could be 'syriable::profile' (compound)

// 1.0.0 — four-field tree decomposition
$file = $string->file;
$package = $string->package;        // just the vendor: 'syriable'
$directories = $string->directories; // ['profile']
$key = $string->key;                 // 'submit.label'

```
For language-file path generation:

```php
// 0.9.0 — string-parse the namespace to build the path
$path = $string->namespace
    ? "lang/vendor/{str_replace('::', '/', $string->namespace)}/{$locale}/{$string->group}.php"
    : "lang/{$locale}/{$string->group}.php";

// 1.0.0 — call the helper
$path = $string->langFilePath($locale);

```
If you implemented `Syriable\Localizer\Contracts\ResultStore`, switch to
listening for the `Events\ScanCompleted` event:

```php
use Syriable\Localizer\Events\ScanCompleted;
use Illuminate\Support\Facades\Event;

Event::listen(ScanCompleted::class, fn (ScanCompleted $e) => $store->put($e->result));

```
## [0.9.0](https://github.com/syriable/laravel-localizer/releases/tag/v0.9.0) - 2026-05-21

First public **beta** release.

### Engine

- Five-stage extraction pipeline: DiscoverFiles → FilterCached → ExtractStrings → NormalizeStrings → PersistCache.
- Seven extractors: Blade, PHP, Vue, JavaScript, TypeScript, Livewire, Inertia.
- Four stable contracts: `Extractor`, `Discoverer`, `Normalizer`, `ScanCache`.
- Immutable PHP 8.4 readonly DTOs: `ScanRequest`, `ScanResult`, `ExtractedString`, `DiscoveredFile`, `SourceLocation`, `StringKind`.
- JSON-on-disk fingerprint cache with atomic writes, content-addressed via xxh128.
- Default cache path is `storage/app/.localizer/cache.json` — outside Laravel's
  `cache:clear` blast radius, so incremental scans survive cache flushes.
- Laravel cache lock for concurrent-safe scanning.
- Three events for ecosystem extension: `ScanStarted`, `FileExtracted`, `ScanCompleted`.
- Single `localizer:scan` Artisan command with `--fresh`, `--extractor`, `--json`, `--summary` flags.
- Fluent `Localizer::in(...)` builder.
- Programmatic `Localizer::scan(ScanRequest)` API.

### Diagnostics

- `ScanResult::$skippedExtensions` — files discovered but with no matching extractor are counted by extension, surfaced via the result DTO, displayed by `localizer:scan` summary, and included in `--json` output.
- `LocalizerException::forFile(DiscoveredFile, \Throwable)` — wraps per-file extractor failures with file context, preserving the original cause via `getPrevious()`.
- `LocalizerException::cacheVersionMismatch(int $found, int $expected)` — typed exception with `--fresh` remediation hint.

### Tooling and packaging

- Comprehensive Pest 4 test suite, larastan static analysis, Pint formatting.
- GitHub Actions workflows: `run-tests`, `phpstan`, `fix-php-code-style-issues`, `update-changelog`.
- PHPStan baseline file (`phpstan-baseline.neon`) committed at empty state.
- `SECURITY.md` with GitHub Security Advisory channel.
