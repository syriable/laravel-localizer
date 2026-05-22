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
 * Extracts translatable strings from Blade templates.
 *
 * Recognised callsites:
 *   - `@lang('key')`
 *   - `__('key')` (in echo or PHP block)
 *   - `trans('key')`, `trans_choice('key', $n)`
 *   - `Lang::get('key')`, `Lang::choice('key', $n)`
 *
 * Dynamic keys (`__($variable)`, concatenated strings) are intentionally
 * ignored — they cannot be statically resolved.
 */
final class BladeExtractor implements Extractor
{
    /**
     * @var list<string> Function names treated as translation calls.
     */
    private const FUNCTIONS = ['__', 'trans', 'trans_choice', '@lang', 'Lang::get', 'Lang::choice'];

    public function __construct(
        private readonly CallExtractor $calls,
        private readonly StringClassifier $classifier,
    ) {}

    public function name(): string
    {
        return 'blade';
    }

    public function patterns(): array
    {
        return ['*.blade.php'];
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
