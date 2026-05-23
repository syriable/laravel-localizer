<?php

declare(strict_types=1);

namespace Syriable\Localizer\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Syriable\Localizer\Analysis\TranslationCallAnalysis;
use Syriable\Localizer\Analysis\TranslationCallAnalyzer;

/**
 * `php artisan localizer:analyze [paths...] [--json] [--key=]`
 *
 * Performs a deeper inspection of translation calls than the regular
 * `localizer:scan` does: for each `__()`, `trans()`, `@lang()`,
 * `trans_choice()`, `Lang::get()` or `Lang::choice()` call it also
 * extracts the replacements array and classifies each placeholder's
 * source expression (variable, object property, function call, …).
 *
 * The structured output is intended for downstream tooling that needs
 * to reason about placeholder shapes — e.g. the AI translation strategy,
 * which uses it to construct context-rich prompts.
 */
final class AnalyzeCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'localizer:analyze
        {paths?* : Paths to analyse (overrides the configured defaults)}
        {--json : Output the full analysis as JSON}
        {--key= : Only include calls matching the given translation key}';

    /**
     * @var string
     */
    protected $description = 'Analyse translation calls and classify each placeholder expression.';

    public function handle(
        TranslationCallAnalyzer $analyzer,
        Filesystem $files,
    ): int {
        $config = config('localizer');

        /** @var list<string> $paths */
        $paths = $this->argument('paths') ?: ($config['paths'] ?? []);

        if ($paths === []) {
            $this->line('<comment>No paths configured. Set `localizer.paths` or pass paths as arguments.</comment>');

            return self::SUCCESS;
        }

        $filter = $this->option('key');
        $analyses = $this->collect($paths, $files, $analyzer, is_string($filter) ? $filter : null);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode(
                array_map(static fn (TranslationCallAnalysis $a): array => $a->toArray(), $analyses),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));

            return self::SUCCESS;
        }

        $this->renderTable($analyses);

        return self::SUCCESS;
    }

    /**
     * @param  list<string>                  $paths
     * @return list<TranslationCallAnalysis>
     */
    private function collect(array $paths, Filesystem $files, TranslationCallAnalyzer $analyzer, ?string $keyFilter): array
    {
        $analyses = [];

        foreach ($this->discoverFiles($paths, $files) as $file) {
            foreach ($analyzer->analyzeFile($file) as $analysis) {
                if ($keyFilter !== null && $analysis->key !== $keyFilter) {
                    continue;
                }

                $analyses[] = $analysis;
            }
        }

        return $analyses;
    }

    /**
     * @param  list<string>     $paths
     * @return iterable<string>
     */
    private function discoverFiles(array $paths, Filesystem $files): iterable
    {
        foreach ($paths as $path) {
            if ($files->isFile($path)) {
                yield $path;

                continue;
            }

            if (! $files->isDirectory($path)) {
                continue;
            }

            $finder = (new Finder)
                ->in($path)
                ->files()
                ->name(['*.php', '*.blade.php'])
                ->ignoreDotFiles(true)
                ->ignoreVCS(true)
                ->followLinks();

            foreach ($finder as $file) {
                $real = $file->getRealPath();

                if ($real !== false) {
                    yield $real;
                }
            }
        }
    }

    /**
     * @param list<TranslationCallAnalysis> $analyses
     */
    private function renderTable(array $analyses): void
    {
        if ($analyses === []) {
            $this->line('<comment>No translation calls were found.</comment>');

            return;
        }

        $rows = array_map(
            static fn (TranslationCallAnalysis $a): array => [
                $a->functionName,
                $a->key,
                count($a->placeholders),
                self::formatPlaceholders($a),
                $a->location->path.':'.$a->location->line,
            ],
            $analyses,
        );

        $this->table(['Function', 'Key', 'Vars', 'Placeholders', 'Location'], $rows);
        $this->line('');
        $this->line('<info>Calls analysed:</info> '.count($analyses));
    }

    private static function formatPlaceholders(TranslationCallAnalysis $a): string
    {
        if ($a->placeholders === []) {
            return '—';
        }

        $parts = array_map(
            static fn ($p): string => $p->placeholder.'='.$p->type->value,
            $a->placeholders,
        );

        return implode(', ', $parts);
    }
}
