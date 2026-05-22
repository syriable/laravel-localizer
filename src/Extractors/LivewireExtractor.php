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
 * Extracts translatable strings from Livewire component files.
 *
 * Livewire components live as `Livewire/*.php` and their views are
 * standard Blade files (handled by BladeExtractor). This extractor
 * specifically targets the component class, recognising the same
 * translation calls as PhpExtractor.
 *
 * It exists as a separate extractor for two reasons:
 *   1. Discoverability — users can include/exclude it explicitly.
 *   2. Future extensibility — Livewire-specific helpers (component
 *      properties, validation messages with `:attribute` placeholders)
 *      can be added here without polluting PhpExtractor.
 */
final class LivewireExtractor implements Extractor
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
        return 'livewire';
    }

    public function patterns(): array
    {
        // Matched against the file BASENAME only (not the full path).
        // Livewire 2 used app/Http/Livewire/ with names like *Livewire*.php
        // or *Component.php. Livewire 3 recommends app/Livewire/ and allows
        // any class name (Counter.php, ShowInvoices.php, etc.).
        //
        // Because the pattern engine operates on basenames, directory-based
        // disambiguation (e.g. "only files under app/Livewire/") is not
        // currently possible without architectural changes. The patterns below
        // cover the most common Livewire naming conventions:
        //
        //   *Livewire*.php  — Livewire 2 / explicit "Livewire" in filename
        //   *Component.php  — Common Livewire 3 suffix convention
        //
        // For arbitrary Livewire 3 names (e.g. Counter.php), rely on the
        // php extractor as a fallback — it extracts the same translation
        // function calls with the same fidelity, only tagged as 'php'.
        return ['*Livewire*.php', '*Component.php'];
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
