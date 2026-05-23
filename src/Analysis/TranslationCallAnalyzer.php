<?php

declare(strict_types=1);

namespace Syriable\Localizer\Analysis;

use Illuminate\Filesystem\Filesystem;
use Syriable\Localizer\Data\StringKind;
use Syriable\Localizer\Support\StringClassifier;

/**
 * Top-level service for analysing translation call sites.
 *
 * Combines:
 *   - {@see TranslationSourceParser} (finds calls + extracts the two
 *     argument shapes we care about)
 *   - {@see PhpExpressionClassifier} (classifies each placeholder value)
 *   - {@see StringClassifier} (decides whether the key is a ShortKey or
 *     a JsonKey, used to shape the suggested lang_example)
 *
 * The output is a list of {@see TranslationCallAnalysis} records — one
 * per call site — suitable for JSON serialisation, AI prompt context, or
 * any other downstream consumer.
 */
final class TranslationCallAnalyzer
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly TranslationSourceParser $parser,
        private readonly PhpExpressionClassifier $classifier,
        private readonly StringClassifier $stringClassifier,
    ) {}

    /**
     * Analyses a single file's source contents.
     *
     * @return list<TranslationCallAnalysis>
     */
    public function analyze(string $contents, string $filePath): array
    {
        $results = [];

        foreach ($this->parser->parse($contents, $filePath) as $call) {
            $placeholders = $this->buildPlaceholders($call['replacements']);

            $results[] = new TranslationCallAnalysis(
                key: $call['key'],
                location: $call['location'],
                functionName: $call['function'],
                placeholders: $placeholders,
                langExample: $this->buildLangExample($call['key'], $placeholders),
            );
        }

        return $results;
    }

    /**
     * Analyses a file on disk by reading its contents.
     *
     * @return list<TranslationCallAnalysis>
     */
    public function analyzeFile(string $absolutePath): array
    {
        if (! $this->files->isFile($absolutePath)) {
            return [];
        }

        return $this->analyze($this->files->get($absolutePath), $absolutePath);
    }

    /**
     * @param  array<string, string>     $replacements
     * @return list<PlaceholderAnalysis>
     */
    private function buildPlaceholders(array $replacements): array
    {
        $result = [];

        foreach ($replacements as $name => $expression) {
            $classified = $this->classifier->classify($expression);

            $result[] = new PlaceholderAnalysis(
                placeholder: ':'.$name,
                source: $expression,
                type: $classified['type'],
                structure: $classified['structure'],
            );
        }

        return $result;
    }

    /**
     * Produces a best-effort lang-file example for the call site.
     *
     * For ShortKey calls, the example value is a humanised rendering of
     * the last dotted segment followed by the placeholder tokens. For
     * JsonKey calls the value mirrors the key (since for JSON entries the
     * key already IS the source text).
     *
     * @param  list<PlaceholderAnalysis> $placeholders
     * @return array<string, string>
     */
    private function buildLangExample(string $key, array $placeholders): array
    {
        $kind = $this->stringClassifier->classify($key);
        $tokens = array_map(static fn (PlaceholderAnalysis $p): string => $p->placeholder, $placeholders);

        if ($kind === StringKind::JsonKey) {
            $value = $tokens === [] ? $key : $key.' ('.implode(', ', $tokens).')';

            return [$key => $value];
        }

        $value = $this->humanise($key);

        if ($tokens !== []) {
            $value .= ' '.implode(' ', $tokens);
        }

        return [$key => $value];
    }

    /**
     * Turns a dotted ShortKey into a human-readable phrase by taking the
     * last segment and replacing word separators with spaces.
     */
    private function humanise(string $key): string
    {
        $segments = explode('.', $key);
        $last = $segments[count($segments) - 1];

        $words = str_replace(['_', '-', '/'], ' ', $last);

        return ucfirst($words);
    }
}
