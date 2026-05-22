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
 * Extracts translatable strings from Vue Single-File Components.
 *
 * Recognised callsites:
 *   - `$t('key')`, `$tc('key', n)`         (vue-i18n composition / options API)
 *   - `t('key')`, `tc('key', n)`           (vue-i18n composition API + Laravel)
 *   - `i18n.t('key')`, `i18n.global.t('key')`
 *   - `trans('key')`, `__('key')`          (community plugins mapping Laravel helpers)
 *
 * The extractor scans the entire file contents — template, script, and
 * style — since vue-i18n calls can appear in any block.
 */
final class VueExtractor implements Extractor
{
    /**
     * @var list<string>
     */
    private const FUNCTIONS = [
        '$t', '$tc', 't', 'tc',
        'i18n.t', 'i18n.global.t',
        'trans', '__',
    ];

    public function __construct(
        private readonly CallExtractor $calls,
        private readonly StringClassifier $classifier,
    ) {}

    public function name(): string
    {
        return 'vue';
    }

    public function patterns(): array
    {
        return ['*.vue'];
    }

    public function extract(DiscoveredFile $file, string $contents): iterable
    {
        foreach ($this->calls->extractCalls($contents, self::FUNCTIONS) as $match) {
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
