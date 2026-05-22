<?php

declare(strict_types=1);

namespace Syriable\Localizer\Extractors;

use Syriable\Localizer\Contracts\Extractor;
use Syriable\Localizer\Data\DiscoveredFile;
use Syriable\Localizer\Data\ExtractedString;
use Syriable\Localizer\Data\SourceLocation;
use Syriable\Localizer\Support\CallExtractor;
use Syriable\Localizer\Support\StringClassifier;

/**
 * Extracts translatable strings from JavaScript source files.
 *
 * Recognised callsites:
 *   - `__('key')`, `trans('key')`         (Laravel-style helpers in JS)
 *   - `t('key')`, `tc('key', n)`          (vue-i18n / i18next)
 *   - `i18n.t('key')`, `i18next.t('key')`
 */
class JavaScriptExtractor implements Extractor
{
    /**
     * @var list<string>
     */
    protected const FUNCTIONS = [
        '__', 'trans', 't', 'tc',
        'i18n.t', 'i18next.t',
    ];

    public function __construct(
        protected readonly CallExtractor $calls,
        protected readonly StringClassifier $classifier,
    ) {}

    public function name(): string
    {
        return 'javascript';
    }

    public function patterns(): array
    {
        return ['*.js', '*.jsx', '*.mjs', '*.cjs'];
    }

    public function extract(DiscoveredFile $file, string $contents): iterable
    {
        foreach ($this->calls->extractCalls($contents, static::FUNCTIONS) as $match) {
            yield new ExtractedString(
                value: $match['value'],
                kind: $this->classifier->classify($match['value']),
                extractor: $this->name(),
                location: new SourceLocation(
                    path: $file->absolutePath,
                    line: $this->calls->lineFor($contents, $match['offset']),
                ),
                package: $this->classifier->packageFor($match['value']),
                directories: $this->classifier->directoriesFor($match['value']),
                file: $this->classifier->fileFor($match['value']),
                key: $this->classifier->keyFor($match['value']),
            );
        }
    }
}
