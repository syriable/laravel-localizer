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
 * Extracts translatable strings from PHP source files.
 *
 * Recognised callsites:
 *   - `__('key')`, `trans('key')`, `trans_choice('key', $n)`
 *   - `Lang::get('key')`, `Lang::choice('key', $n)`
 *   - `app('translator')->get('key')` is intentionally NOT supported —
 *     it's rare and ambiguous; users who need it can register a
 *     custom extractor or add a normalizer.
 */
final class PhpExtractor implements Extractor
{
    /**
     * @var list<string>
     */
    private const FUNCTIONS = ['__', 'trans', 'trans_choice', 'Lang::get', 'Lang::choice'];

    public function __construct(
        private readonly CallExtractor $calls,
        private readonly StringClassifier $classifier,
    ) {}

    public function name(): string
    {
        return 'php';
    }

    public function patterns(): array
    {
        return ['*.php'];
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
