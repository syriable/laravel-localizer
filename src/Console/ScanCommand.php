<?php

declare(strict_types=1);

namespace Syriable\Localizer\Console;

use Illuminate\Console\Command;
use Syriable\Localizer\Data\ScanRequest;
use Syriable\Localizer\Data\ScanResult;
use Syriable\Localizer\Localizer;

/**
 * `php artisan localizer:scan [paths...] [--fresh] [--extractor=...] [--json] [--summary]`
 *
 * The single Artisan entrypoint for the engine. Returns exit code 0 on
 * success and a non-zero code only on configuration/invocation errors —
 * an empty result is not an error.
 */
final class ScanCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'localizer:scan
        {paths?* : Paths to scan (overrides the configured defaults)}
        {--fresh : Ignore the cache and re-scan all files}
        {--extractor=* : Restrict scanning to specific extractors}
        {--json : Output the full scan result as JSON}
        {--summary : Output only summary statistics}';

    /**
     * @var string
     */
    protected $description = 'Scan application source files for translatable strings.';

    public function handle(Localizer $localizer): int
    {
        $request = $this->buildRequest($localizer);

        $result = $localizer->scan($request);

        if ((bool) $this->option('json')) {
            // Use $this->line() (not writeln with OUTPUT_RAW) so Laravel's
            // command-test harness can observe the output. Symfony's
            // OutputFormatter automatically strips ANSI escape sequences
            // when stdout is not a TTY, so piping `localizer:scan --json`
            // to a file or another command still yields clean JSON.
            $this->line((string) json_encode(
                $result->toArray(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));

            return self::SUCCESS;
        }

        if ((bool) $this->option('summary')) {
            $this->renderSummary($result);

            return self::SUCCESS;
        }

        $this->renderTable($result);
        $this->renderSummary($result);

        return self::SUCCESS;
    }

    private function buildRequest(Localizer $localizer): ScanRequest
    {
        $config = config('localizer');

        /** @var list<string> $paths */
        $paths = $this->argument('paths') ?: $config['paths'];

        /** @var list<string> $extractors */
        $extractors = $this->option('extractor');

        return new ScanRequest(
            paths: $paths,
            exclude: $config['exclude'] ?? [],
            extractors: $extractors !== [] ? $extractors : null,
            useCache: ! (bool) $this->option('fresh'),
        );
    }

    private function renderTable(ScanResult $result): void
    {
        $unique = $result->unique();

        if ($unique === []) {
            $this->line('<comment>No translatable strings were found.</comment>');

            return;
        }

        $rows = array_map(
            static fn ($s): array => [
                $s->kind->value,
                $s->extractor,
                $s->package ?? '—',
                $s->file ?? '—',
                self::truncate($s->value, 60),
                $s->location->path.':'.$s->location->line,
            ],
            $unique,
        );

        $this->table(['Kind', 'Extractor', 'Package', 'File', 'Value', 'Location'], $rows);
    }

    private function renderSummary(ScanResult $result): void
    {
        $unique = count($result->unique());

        $this->line('');
        $this->line('<info>Files scanned    :</info> '.$result->filesScanned);
        $this->line('<info>Files from cache :</info> '.$result->filesFromCache);
        $this->line('<info>Files fresh      :</info> '.$result->filesFresh());
        $this->line('<info>Strings (total)  :</info> '.$result->count());
        $this->line('<info>Strings (unique) :</info> '.$unique);
        $this->line('<info>Duration         :</info> '.number_format($result->durationMs, 1).' ms');

        if ($result->skippedExtensions !== []) {
            // Show a short hint with the top few unmatched extensions so
            // users notice when a file type they care about has no
            // extractor registered (e.g. they added .twig but forgot
            // to register a TwigExtractor).
            //
            // Copy to a local first — $result is readonly, so we can't
            // arsort() the array property indirectly.
            $skipped = $result->skippedExtensions;
            arsort($skipped);

            $top = array_slice($skipped, 0, 5, preserve_keys: true);
            $totalSkipped = array_sum($skipped);

            $extensions = [];
            foreach ($top as $ext => $count) {
                $extensions[] = ".{$ext} ({$count})";
            }

            $this->line('');
            $this->line(sprintf(
                '<comment>%d file(s) had no matching extractor. Top extensions: %s</comment>',
                $totalSkipped,
                implode(', ', $extensions),
            ));
            $this->line('<comment>Register an extractor or extend `paths`/`exclude` in config/localizer.php.</comment>');
        }
    }

    private static function truncate(string $value, int $max): string
    {
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max - 1).'…';
    }
}
