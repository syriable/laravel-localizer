<?php

declare(strict_types=1);

namespace Syriable\Localizer\Analysis;

use Syriable\Localizer\Data\SourceLocation;

/**
 * The full analysis of one translation call site.
 *
 * Produced by {@see TranslationCallAnalyzer} for each `__()`, `trans()`,
 * `@lang()`, `Lang::get()`, `trans_choice()`, or `Lang::choice()`
 * invocation discovered in the source code.
 *
 * The structure is designed to feed downstream tooling — particularly the
 * AI translation strategy — with enough context to understand what each
 * placeholder represents in the running application without having to
 * re-parse the source file.
 */
final readonly class TranslationCallAnalysis
{
    /**
     * @param list<PlaceholderAnalysis> $placeholders Placeholders extracted from the second
     *                                                argument array (or third, for *_choice).
     * @param array<string, string>     $langExample  A best-effort illustration of what the
     *                                                key would look like in the lang file —
     *                                                one entry mapping the key to a sentence
     *                                                that references each placeholder.
     */
    public function __construct(
        public string $key,
        public SourceLocation $location,
        public string $functionName,
        public array $placeholders,
        public array $langExample,
    ) {}

    /**
     * @return array{
     *     key: string,
     *     function: string,
     *     location: array{path: string, line: int, column: int},
     *     placeholders: list<array<string, mixed>>,
     *     lang_example: array<string, string>
     * }
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'function' => $this->functionName,
            'location' => $this->location->toArray(),
            'placeholders' => array_map(
                static fn (PlaceholderAnalysis $p): array => $p->toArray(),
                $this->placeholders,
            ),
            'lang_example' => $this->langExample,
        ];
    }
}
