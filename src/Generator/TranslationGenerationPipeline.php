<?php

declare(strict_types=1);

namespace Syriable\Localizer\Generator;

use Syriable\Localizer\Contracts\AnalysisAwareStrategy;
use Syriable\Localizer\Contracts\LocaleAwareStrategy;
use Syriable\Localizer\Data\ExtractedString;
use Syriable\Localizer\Data\GenerationRequest;
use Syriable\Localizer\Data\GenerationResult;
use Syriable\Localizer\Data\StringKind;

/**
 * Top-level orchestrator for the translation file generation process.
 *
 * Groups strings from the scan result by their target file path, then
 * delegates per-file work to the appropriate generator:
 *
 *   - ShortKey strings → PHP files via `TranslationFileGenerator`
 *   - JsonKey strings  → JSON files via `TranslationJsonFileGenerator`
 *
 * Namespace filtering: when `GenerationRequest::$namespace` is set, only
 * strings whose `package` field matches are processed. Strings with a null
 * package are considered "application" strings and are included when the
 * filter is null (no filter) or when it is explicitly set to the empty
 * string `""`. JsonKey strings always have a null package, so they are
 * included for null/empty namespace filters and excluded for specific ones.
 */
final class TranslationGenerationPipeline
{
    public function __construct(
        private readonly TranslationFileGenerator $fileGenerator,
        private readonly TranslationJsonFileGenerator $jsonFileGenerator,
        private readonly StrategyRegistry $strategies,
    ) {}

    public function run(GenerationRequest $request): GenerationResult
    {
        $result = new GenerationResult(
            locale: $request->locale,
            dryRun: $request->dryRun,
        );

        $strategy = $this->strategies->get($request->strategy);

        if ($strategy instanceof LocaleAwareStrategy) {
            $strategy = $strategy->withLocale($request->locale);
        }

        if ($strategy instanceof AnalysisAwareStrategy && $request->callAnalyses !== []) {
            $strategy = $strategy->withAnalysis($request->callAnalyses);
        }

        foreach ($this->groupPhpStrings($request) as $absolutePath => $strings) {
            $outcome = $this->fileGenerator->generate(
                absolutePath: $absolutePath,
                strings: $strings,
                strategy: $strategy,
                force: $request->force,
                dryRun: $request->dryRun,
            );

            $this->recordOutcome($result, $absolutePath, $outcome);
        }

        foreach ($this->groupJsonStrings($request) as $absolutePath => $strings) {
            $outcome = $this->jsonFileGenerator->generate(
                absolutePath: $absolutePath,
                strings: $strings,
                strategy: $strategy,
                force: $request->force,
                dryRun: $request->dryRun,
            );

            $this->recordOutcome($result, $absolutePath, $outcome);
        }

        return $result;
    }

    /**
     * Groups ShortKey strings by their absolute target PHP file path.
     *
     * Paths are always normalised to forward-slash separators so the
     * grouping key is stable across operating systems.
     *
     * @return array<string, list<ExtractedString>>
     */
    private function groupPhpStrings(GenerationRequest $request): array
    {
        $groups = [];
        $resolvedBase = $this->resolveBase($request->basePath);

        foreach ($request->result->unique() as $string) {
            if ($string->kind !== StringKind::ShortKey) {
                continue;
            }

            if (! $this->matchesNamespaceFilter($string, $request->namespace)) {
                continue;
            }

            $relPath = $string->langFilePath($request->locale);
            $absolutePath = $resolvedBase.'/'.ltrim($relPath, '/');

            $groups[$absolutePath][] = $string;
        }

        return $groups;
    }

    /**
     * Groups JsonKey strings by their absolute target JSON file path.
     *
     * All JsonKey strings for a given locale map to the same file
     * (`lang/{locale}.json`), so this typically returns a single-entry map.
     *
     * @return array<string, list<ExtractedString>>
     */
    private function groupJsonStrings(GenerationRequest $request): array
    {
        $groups = [];
        $resolvedBase = $this->resolveBase($request->basePath);

        foreach ($request->result->unique() as $string) {
            if ($string->kind !== StringKind::JsonKey) {
                continue;
            }

            if (! $this->matchesNamespaceFilter($string, $request->namespace)) {
                continue;
            }

            $relPath = $string->langFilePath($request->locale);
            $absolutePath = $resolvedBase.'/'.ltrim($relPath, '/');

            $groups[$absolutePath][] = $string;
        }

        return $groups;
    }

    private function resolveBase(string $basePath): string
    {
        $basePath = rtrim($basePath, '/\\');
        $realBase = realpath($basePath);
        $resolved = $realBase !== false ? $realBase : $basePath;

        return str_replace('\\', '/', $resolved);
    }

    private function matchesNamespaceFilter(ExtractedString $string, ?string $namespace): bool
    {
        if ($namespace === null) {
            return true;
        }

        if ($namespace === '') {
            return $string->package === null;
        }

        return $string->package === $namespace;
    }

    private function recordOutcome(GenerationResult $result, string $absolutePath, GeneratedFileOutcome $outcome): void
    {
        if ($outcome->written) {
            $result->written[] = $absolutePath;
            $result->keysAdded += $outcome->keysAdded;

            if ($outcome->preview !== null) {
                $result->preview[$absolutePath] = $outcome->preview;
            }
        } else {
            $result->skipped[] = $absolutePath;
        }
    }
}
