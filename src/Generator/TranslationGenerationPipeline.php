<?php

declare(strict_types=1);

namespace Syriable\Localizer\Generator;

use Syriable\Localizer\Data\ExtractedString;
use Syriable\Localizer\Data\GenerationRequest;
use Syriable\Localizer\Data\GenerationResult;
use Syriable\Localizer\Data\StringKind;

/**
 * Top-level orchestrator for the translation file generation process.
 *
 * Groups ShortKey strings from the scan result by their target language
 * file path, then delegates per-file work to `TranslationFileGenerator`.
 *
 * Namespace filtering: when `GenerationRequest::$namespace` is set, only
 * strings whose `package` field matches are processed. Strings with a null
 * package are considered "application" strings and are included when the
 * filter is null (no filter) or when it is explicitly set to the empty
 * string `""`.
 */
final class TranslationGenerationPipeline
{
    public function __construct(
        private readonly TranslationFileGenerator $fileGenerator,
        private readonly StrategyRegistry $strategies,
    ) {}

    public function run(GenerationRequest $request): GenerationResult
    {
        $result = new GenerationResult(
            locale: $request->locale,
            dryRun: $request->dryRun,
        );

        $strategy = $this->strategies->get($request->strategy);
        $groups = $this->groupStrings($request);

        foreach ($groups as $absolutePath => $strings) {
            $outcome = $this->fileGenerator->generate(
                absolutePath: $absolutePath,
                strings: $strings,
                strategy: $strategy,
                force: $request->force,
                dryRun: $request->dryRun,
            );

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

        return $result;
    }

    /**
     * Groups filtered strings by their absolute target file path.
     *
     * Paths are always normalised to forward-slash separators so the
     * grouping key is stable across operating systems and so downstream
     * string assertions (e.g. test `toContain('vendor/acme')`) behave
     * identically on Windows and POSIX.
     *
     * @return array<string, list<ExtractedString>>
     */
    private function groupStrings(GenerationRequest $request): array
    {
        $groups = [];
        $basePath = rtrim($request->basePath, '/\\');
        $realBase = realpath($basePath);

        $resolvedBase = $realBase !== false ? $realBase : $basePath;
        $resolvedBase = str_replace('\\', '/', $resolvedBase);

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
}
