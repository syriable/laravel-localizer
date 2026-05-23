<?php

declare(strict_types=1);

namespace Syriable\Localizer\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Syriable\Localizer\Analysis\TranslationCallAnalysis;
use Syriable\Localizer\Analysis\TranslationCallAnalyzer;
use Syriable\Localizer\Contracts\AnalysisAwareStrategy;
use Syriable\Localizer\Data\GenerationRequest;
use Syriable\Localizer\Data\GenerationResult;
use Syriable\Localizer\Data\ScanRequest;
use Syriable\Localizer\Generator\TranslationGenerationPipeline;
use Syriable\Localizer\Localizer;

/**
 * `php artisan localizer:generate`
 *
 * Scans the application for translatable strings and generates or updates
 * translation files with placeholder values for any missing keys.
 *
 * Before generating, the command runs a static call-site analysis on the
 * same source paths so that strategies implementing
 * {@see AnalysisAwareStrategy} (notably the
 * `ai` strategy) receive placeholder context and can produce richer output.
 *
 * The command never overwrites existing translation values unless `--force`
 * is explicitly passed. In `--dry-run` mode it prints the would-be file
 * contents without touching the filesystem.
 */
final class GenerateCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'localizer:generate
        {--locale=     : Locale to generate files for (e.g. en, fr)}
        {--all-locales : Generate files for all locales configured in localizer.generator.locales}
        {--dry-run     : Preview the output without writing any files}
        {--force       : Overwrite existing translation values}
        {--namespace=  : Restrict generation to a vendor package namespace}
        {--strategy=   : Value generation strategy: humanized (default), key, empty, or ai}
        {--fresh       : Ignore the cache and re-scan all files before generating}
        {--no-analyze  : Skip the call-site analysis step (disables placeholder context)}';

    /**
     * @var string
     */
    protected $description = 'Generate translation files for missing keys, with optional AI translation and placeholder analysis.';

    public function handle(
        Localizer $localizer,
        TranslationGenerationPipeline $pipeline,
        TranslationCallAnalyzer $analyzer,
        Filesystem $files,
    ): int {
        $locales = $this->resolveLocales();

        if ($locales === []) {
            $this->line('<comment>No locales configured. Pass --locale=en or add locales to localizer.generator.locales.</comment>');

            return self::SUCCESS;
        }

        $strategy = $this->resolveStrategy();
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        /** @var string|null $namespace */
        $namespace = $this->option('namespace') ?: null;

        $scanResult = $localizer->scan($this->buildScanRequest($localizer));

        if ($scanResult->count() === 0) {
            $this->line('<comment>No translatable strings found. Nothing to generate.</comment>');

            return self::SUCCESS;
        }

        $callAnalyses = (bool) $this->option('no-analyze')
            ? []
            : $this->runAnalysis($analyzer, $files);

        $overallWritten = 0;
        $overallSkipped = 0;
        $overallKeys = 0;

        foreach ($locales as $locale) {
            $request = new GenerationRequest(
                result: $scanResult,
                locale: $locale,
                strategy: $strategy,
                basePath: base_path(),
                dryRun: $dryRun,
                force: $force,
                namespace: $namespace,
                callAnalyses: $callAnalyses,
            );

            $result = $pipeline->run($request);

            $this->renderLocaleResult($result);

            $overallWritten += $result->filesWritten();
            $overallSkipped += $result->filesSkipped();
            $overallKeys += $result->keysAdded;
        }

        if (count($locales) > 1) {
            $this->line('');
            $this->line('<info>All locales complete.</info>');
            $this->line("<info>Files written : </info>{$overallWritten}");
            $this->line("<info>Files skipped : </info>{$overallSkipped}");
            $this->line("<info>Keys added    : </info>{$overallKeys}");
        }

        return self::SUCCESS;
    }

    /**
     * Runs the call-site analyzer over the configured source paths and
     * returns analyses keyed by translation key. When multiple call sites
     * share the same key, the first one found takes precedence.
     *
     * @return array<string, TranslationCallAnalysis>
     */
    private function runAnalysis(TranslationCallAnalyzer $analyzer, Filesystem $files): array
    {
        $config = config('localizer');

        /** @var list<string> $paths */
        $paths = $config['paths'] ?? [];

        $analyses = [];

        foreach ($this->discoverSourceFiles($paths, $files) as $path) {
            foreach ($analyzer->analyzeFile($path) as $analysis) {
                if (! isset($analyses[$analysis->key])) {
                    $analyses[$analysis->key] = $analysis;
                }
            }
        }

        return $analyses;
    }

    /**
     * @param  list<string>     $paths
     * @return iterable<string>
     */
    private function discoverSourceFiles(array $paths, Filesystem $files): iterable
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
     * @return list<string>
     */
    private function resolveLocales(): array
    {
        if ((bool) $this->option('all-locales')) {
            /** @var list<string> $configured */
            $configured = config('localizer.generator.locales', []);

            if ($configured === []) {
                $this->warn('--all-locales was passed but localizer.generator.locales is empty.');
            }

            return $configured;
        }

        /** @var string|null $locale */
        $locale = $this->option('locale');

        if ($locale !== null && $locale !== '') {
            return [$locale];
        }

        /** @var string $appLocale */
        $appLocale = config('app.locale', 'en');

        return [$appLocale];
    }

    private function resolveStrategy(): string
    {
        /** @var string|null $strategy */
        $strategy = $this->option('strategy');

        if ($strategy !== null && $strategy !== '') {
            return $strategy;
        }

        return (string) config('localizer.generator.strategy', 'humanized');
    }

    private function buildScanRequest(Localizer $localizer): ScanRequest
    {
        $config = config('localizer');

        return new ScanRequest(
            paths: $config['paths'] ?? [],
            exclude: $config['exclude'] ?? [],
            useCache: ! (bool) $this->option('fresh'),
        );
    }

    private function renderLocaleResult(GenerationResult $result): void
    {
        $dryLabel = $result->dryRun ? ' <comment>[DRY RUN]</comment>' : '';

        $this->line('');
        $this->line("<info>Locale: {$result->locale}</info>{$dryLabel}");

        if ($result->filesWritten() === 0 && $result->filesSkipped() > 0) {
            $this->line('<comment>  No missing keys. All files up to date.</comment>');

            return;
        }

        foreach ($result->written as $path) {
            $preview = $result->preview[$path] ?? null;

            if ($preview !== null) {
                $this->line("  <info>WOULD WRITE</info> {$path}");
                $this->renderPreview($preview);
            } else {
                $this->line("  <info>WRITTEN</info> {$path}");
            }
        }

        $this->line('');
        $this->line("  Files written : {$result->filesWritten()}");
        $this->line("  Files skipped : {$result->filesSkipped()}");
        $this->line("  Keys added    : {$result->keysAdded}");
    }

    private function renderPreview(string $content): void
    {
        foreach (explode("\n", $content) as $line) {
            $this->line("    {$line}");
        }
    }
}
