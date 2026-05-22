# Changelog

All notable changes to `syriable/laravel-localizer` will be documented in this file.

## [Unreleased]

### Changed — BREAKING

This release replaces the old "group + namespace" decomposition on `ExtractedString` with a richer **translation tree** model that mirrors Laravel's actual on-disk language-file layout. Beta-period breaking change — see migration notes below.

**Old shape (0.9.0):**

```php
$string->group;     // 'pagination' or null
$string->namespace; // 'syriable::profile' or null
```

**New shape:**

```php
$string->package;       // 'syriable' (vendor name before `::`), or null
$string->directories;   // ['profile', 'button', 'form'] — directory chain, ordered
$string->file;          // 'buttons' (PHP file name without `.php`)
$string->key;           // 'submit.label' (dotted path inside the file's array)
$string->filePath();    // 'profile/button/form/buttons.php' (relative file path) or null
```

The new model is unambiguous, supports unlimited directory nesting, and maps directly to filesystem paths. Downstream language-file writers can now compute the destination with no string parsing:

- Plain: `lang/{locale}/{filePath()}`
- Packaged: `lang/vendor/{package}/{locale}/{filePath()}`

### Changed

- `ScanResult::groupedByShortKeyGroup()` renamed to `groupedByFile()`. Behaviour is the same — buckets ShortKey strings by the file they target.
- `ScanCommand` table output replaces the "Group" column with "Package" and "File" columns.
- `ExtractedString::fingerprint()` now incorporates the full decomposition (package, directories, file, value) so values at different filesystem targets are never deduplicated together.
- Cache schema version bumped from 1 to 2. Caches written by 0.9.0 are automatically treated as empty on first scan under this release — every file will be re-extracted under the new decomposition. No manual `--fresh` required.

### Added

- `StringClassifier::packageFor()`, `directoriesFor()`, `fileFor()`, `keyFor()` — the four-field decomposition API.
- `ExtractedString::filePath()` — returns the relative path to the target PHP file (e.g. `"profile/buttons.php"`) for ShortKeys, or `null` for JsonKeys.
- `ExtractedString::langFilePath(string $locale)` — returns the full on-disk path to the language file, including the `lang/` prefix and the `vendor/{package}/` segment when applicable. For JsonKeys, returns `"lang/{locale}.json"`. The locale string is validated against a Laravel-compatible character set to prevent path-traversal injection.
- Constructor invariants on `ExtractedString` validate that JsonKeys have no decomposition fields and that directory segments are non-empty strings.

### Migration from 0.9.0

If you wrote any normalizer or consumer code against 0.9.0:

```php
// 0.9.0 — group + namespace
$file = $string->group ?? '';
$namespace = $string->namespace;  // could be 'syriable::profile' (compound)

// 0.9.1 — clean decomposition
$file = $string->file;
$package = $string->package;       // just the vendor: 'syriable'
$directories = $string->directories; // ['profile']
$key = $string->key;                // 'submit.label' (was implicit in `value`)
```

For language-file generation:

```php
// 0.9.0 — string-parse the namespace to build the path
$path = $string->namespace
    ? "lang/vendor/{str_replace('::', '/', $string->namespace)}/{$locale}/{$string->group}.php"
    : "lang/{$locale}/{$string->group}.php";

// 0.9.1 — fields are already filesystem-shaped
$path = $string->package
    ? "lang/vendor/{$string->package}/{$locale}/{$string->filePath()}"
    : "lang/{$locale}/{$string->filePath()}";
```

## [0.9.0] - 2026-05-21

First public **beta** release. The package is production-quality engineering-wise
(290 passing tests, PHPStan at level max, no risky warnings), but the public
API is not yet locked. We're releasing 0.9.x to gather real-world feedback
before committing to 1.0.0 semver. Expect minor API adjustments in the 0.9.x
line based on what users actually need.

If you adopt 0.9.x, please pin a specific version (`^0.9.0`) rather than
`dev-main` — patch releases will be safe to upgrade, but minor releases
(0.10.x, 0.11.x) may introduce breaking changes during the beta period.

### Engine

- Five-stage extraction pipeline: DiscoverFiles → FilterCached → ExtractStrings → NormalizeStrings → PersistCache.
- Seven extractors: Blade, PHP, Vue, JavaScript, TypeScript, Livewire, Inertia.
- Five stable contracts: `Extractor`, `Discoverer`, `Normalizer`, `ScanCache`, `ResultStore`.
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

- Comprehensive Pest 4 test suite, Larastan max static analysis, Pint formatting.
- GitHub Actions workflows: `run-tests`, `phpstan`, `fix-php-code-style-issues`, `update-changelog`.
- PHPStan baseline file (`phpstan-baseline.neon`) committed at empty state.
- `SECURITY.md` with GitHub Security Advisory channel.
- `BETA-TESTING.md` with a 15-minute verification checklist.

[Unreleased]: https://github.com/syriable/laravel-localizer/compare/v0.9.0...HEAD
[0.9.0]: https://github.com/syriable/laravel-localizer/releases/tag/v0.9.0
