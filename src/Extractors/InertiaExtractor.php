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
 * Extracts translatable strings from Inertia page components.
 *
 * Inertia pages are Vue or React components. Since both syntaxes share
 * the same call shape (`t('key')`, `$t('key')`, `trans('key')`), this
 * extractor is essentially a Vue/React-friendly superset matching any
 * file under `resources/js/Pages/`.
 *
 * It matches the broader set of file extensions used by Inertia
 * (`.vue`, `.tsx`, `.jsx`) but only triggers for files whose names
 * suggest an Inertia page. Users with different conventions can override
 * the patterns in config.
 */
final class InertiaExtractor implements Extractor
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
        return 'inertia';
    }

    public function patterns(): array
    {
        // Inertia Page filenames are conventionally `Page.vue`, `Page.tsx`,
        // etc. — the basename match is intentionally broad. If both this
        // and VueExtractor are registered, the FIRST registered wins:
        // register InertiaExtractor BEFORE VueExtractor to give Inertia
        // pages priority.
        return ['*Page.vue', '*Page.tsx', '*Page.jsx', '*Layout.vue', '*Layout.tsx'];
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
