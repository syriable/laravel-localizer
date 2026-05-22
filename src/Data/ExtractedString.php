<?php

declare(strict_types=1);

namespace Syriable\Localizer\Data;

/**
 * A single translatable string extracted from a source file.
 *
 * Instances are immutable. Equality is determined by {@see fingerprint()}.
 *
 * For ShortKeys, the value is decomposed into the translation file's
 * filesystem target plus the key inside that file:
 *
 *   - `package`     — vendor namespace (the segment before `::`), or null.
 *                     Maps to `lang/vendor/{package}/`.
 *   - `directories` — directory chain inside the locale directory,
 *                     in order. Empty list for plain keys.
 *   - `file`        — name of the target PHP file (without `.php`).
 *   - `key`         — dotted path inside the PHP file's returned array.
 *
 * Putting them together, the destination file is:
 *
 *   plain:      lang/{locale}/{directories...}/{file}.php
 *   namespaced: lang/vendor/{package}/{locale}/{directories...}/{file}.php
 *
 * And the value within that file is found at `data_get($array, $key)`.
 *
 * For JsonKeys, all four fields are null/empty — the value itself IS
 * the key, looked up directly in `lang/{locale}.json`.
 */
final readonly class ExtractedString
{
    /**
     * @param list<string> $directories
     */
    public function __construct(
        public string $value,
        public StringKind $kind,
        public string $extractor,
        public SourceLocation $location,
        public ?string $package = null,
        public array $directories = [],
        public ?string $file = null,
        public ?string $key = null,
    ) {
        if ($value === '') {
            throw new \InvalidArgumentException('ExtractedString value cannot be empty.');
        }

        if ($extractor === '') {
            throw new \InvalidArgumentException('ExtractedString extractor name cannot be empty.');
        }

        if ($kind === StringKind::ShortKey) {
            if ($file === null) {
                throw new \InvalidArgumentException(
                    'ExtractedString of kind ShortKey must have a non-null file.',
                );
            }

            if ($key === null) {
                throw new \InvalidArgumentException(
                    'ExtractedString of kind ShortKey must have a non-null key.',
                );
            }
        }

        if ($kind === StringKind::JsonKey) {
            if ($package !== null) {
                throw new \InvalidArgumentException(
                    'ExtractedString of kind JsonKey must have a null package.',
                );
            }

            if ($directories !== []) {
                throw new \InvalidArgumentException(
                    'ExtractedString of kind JsonKey must have an empty directories list.',
                );
            }

            if ($file !== null) {
                throw new \InvalidArgumentException(
                    'ExtractedString of kind JsonKey must have a null file.',
                );
            }

            if ($key !== null) {
                throw new \InvalidArgumentException(
                    'ExtractedString of kind JsonKey must have a null key.',
                );
            }
        }

        self::assertNonEmptyStringList(
            $directories,
            'ExtractedString directories must be a list of non-empty strings.',
        );
    }

    /**
     * @param array<int, mixed> $values
     */
    private static function assertNonEmptyStringList(array $values, string $message): void
    {
        foreach ($values as $value) {
            if (! is_string($value) || $value === '') {
                throw new \InvalidArgumentException($message);
            }
        }
    }

    /**
     * A stable, content-addressable identifier for this string.
     *
     * Uses xxh128 — collision-resistant, ~10x faster than sha1, and
     * deterministic across runs and machines.
     *
     * Includes package and directory chain so that "syriable::profile/
     * buttons.submit" and "buttons.submit" are NOT considered duplicates
     * (they refer to different translation files).
     */
    public function fingerprint(): string
    {
        return hash(
            'xxh128',
            implode('|', [
                $this->kind->value,
                $this->package ?? '',
                implode('/', $this->directories),
                $this->file ?? '',
                $this->value,
            ]),
        );
    }

    /**
     * Returns the relative filesystem path to the target PHP file
     * (without locale or `lang/` prefix), or null for JSON keys.
     *
     *   "pagination.next"                            "pagination.php"
     *   "profile/buttons.submit"                     "profile/buttons.php"
     *   "profile/button/form/icon.submit.label"      "profile/button/form/icon.php"
     *   "syriable::profile/buttons.submit.label"    "profile/buttons.php"
     *   "Welcome back"                               null
     *
     * Note: the package prefix is NOT included — downstream consumers
     * typically combine this with the `package` field to compute the
     * full path under `lang/vendor/{package}/{locale}/`.
     */
    public function filePath(): ?string
    {
        if ($this->kind !== StringKind::ShortKey) {
            return null;
        }

        $segments = [...$this->directories, $this->file];

        return implode('/', $segments).'.php';
    }

    /**
     * Returns the full on-disk path to the language file this string
     * belongs in, relative to the application root.
     *
     * The shape depends on kind and whether the string is packaged:
     *
     *   JsonKey                lang/{locale}.json
     *   ShortKey, plain        lang/{locale}/{filePath}
     *   ShortKey, packaged     lang/vendor/{package}/{locale}/{filePath}
     *
     * Examples (locale = "en"):
     *
     *   "Welcome back"
     *     → lang/en.json
     *
     *   "pagination.next"
     *     → lang/en/pagination.php
     *
     *   "profile/button/form/icon.submit.label"
     *     → lang/en/profile/button/form/icon.php
     *
     *   "syriable::profile/buttons.submit.label"
     *     → lang/vendor/syriable/en/profile/buttons.php
     *
     *   "syriable::profile/button/form/icon.submit.label"
     *     → lang/vendor/syriable/en/profile/button/form/icon.php
     *
     * The `$locale` argument is validated against the same character set
     * used for other identifiers in the package (letters, digits, `_`,
     * `-`) to prevent path traversal via crafted locale strings. If you
     * need a different on-disk layout, build the path yourself from
     * `$string->package`, `$string->directories`, and `$string->file`.
     */
    public function langFilePath(string $locale): string
    {
        if ($locale === '' || preg_match('/^[A-Za-z0-9_-]+$/', $locale) !== 1) {
            throw new \InvalidArgumentException(
                "Invalid locale [{$locale}] — expected letters, digits, `_` or `-` only.",
            );
        }

        if ($this->kind === StringKind::JsonKey) {
            return "lang/{$locale}.json";
        }

        $relative = $this->filePath();
        \assert($relative !== null); // ShortKey always has a filePath

        return $this->package === null
            ? "lang/{$locale}/{$relative}"
            : "lang/vendor/{$this->package}/{$locale}/{$relative}";
    }

    /**
     * @return array{
     *     value: string,
     *     kind: string,
     *     extractor: string,
     *     location: array{path: string, line: int, column: int},
     *     package: string|null,
     *     directories: list<string>,
     *     file: string|null,
     *     key: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'kind' => $this->kind->value,
            'extractor' => $this->extractor,
            'location' => $this->location->toArray(),
            'package' => $this->package,
            'directories' => $this->directories,
            'file' => $this->file,
            'key' => $this->key,
        ];
    }

    /**
     * Returns a new instance with a different value.
     *
     * Normalizers use this to rewrite the string text while preserving
     * the source location, extractor name, and decomposition fields.
     */
    public function withValue(string $value): self
    {
        return new self(
            value: $value,
            kind: $this->kind,
            extractor: $this->extractor,
            location: $this->location,
            package: $this->package,
            directories: $this->directories,
            file: $this->file,
            key: $this->key,
        );
    }

    /**
     * Returns a new instance with a different kind.
     *
     * Reclassifying between {@see StringKind::ShortKey} and
     * {@see StringKind::JsonKey} requires adjusting the decomposition
     * fields appropriately (ShortKeys must have file+key; JsonKeys must
     * have null/empty for all four). Pass them explicitly.
     *
     * @param list<string> $directories
     */
    public function withKind(
        StringKind $kind,
        ?string $package = null,
        array $directories = [],
        ?string $file = null,
        ?string $key = null,
    ): self {
        return new self(
            value: $this->value,
            kind: $kind,
            extractor: $this->extractor,
            location: $this->location,
            package: $package,
            directories: $directories,
            file: $file,
            key: $key,
        );
    }

    /**
     * Reconstruct an instance from its array form.
     *
     * @param array{
     *     value: string,
     *     kind: string,
     *     extractor: string,
     *     location: array{path: string, line: int, column?: int},
     *     package?: string|null,
     *     directories?: list<string>,
     *     file?: string|null,
     *     key?: string|null
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            value: $data['value'],
            kind: StringKind::from($data['kind']),
            extractor: $data['extractor'],
            location: new SourceLocation(
                path: $data['location']['path'],
                line: $data['location']['line'],
                column: $data['location']['column'] ?? 0,
            ),
            package: $data['package'] ?? null,
            directories: $data['directories'] ?? [],
            file: $data['file'] ?? null,
            key: $data['key'] ?? null,
        );
    }
}
