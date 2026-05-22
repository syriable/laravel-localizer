<?php

declare(strict_types=1);

namespace Syriable\Localizer;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\ServiceProvider;
use Syriable\Localizer\Cache\FileScanCache;
use Syriable\Localizer\Cache\NullScanCache;
use Syriable\Localizer\Console\ScanCommand;
use Syriable\Localizer\Contracts\Discoverer;
use Syriable\Localizer\Contracts\Extractor;
use Syriable\Localizer\Contracts\ScanCache;
use Syriable\Localizer\Pipeline\DiscoverFiles;
use Syriable\Localizer\Pipeline\ExtractStrings;
use Syriable\Localizer\Pipeline\FilterCached;
use Syriable\Localizer\Pipeline\NormalizeStrings;
use Syriable\Localizer\Pipeline\PersistCache;
use Syriable\Localizer\Pipeline\PipelineDiscoverer;
use Syriable\Localizer\Pipeline\ScanPipeline;
use Syriable\Localizer\Support\AtomicWriter;
use Syriable\Localizer\Support\CallExtractor;
use Syriable\Localizer\Support\ExtractorRegistry;
use Syriable\Localizer\Support\FileHasher;
use Syriable\Localizer\Support\PathMatcher;
use Syriable\Localizer\Support\StringClassifier;

/**
 * The single service provider for the package.
 *
 * Wires every binding the engine needs. The provider is split into
 * `register()` (bindings only, no side effects) and `boot()` (publishing,
 * commands), following Laravel's own convention.
 */
final class LocalizerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/localizer.php', 'localizer');

        $this->registerSupportServices();
        $this->registerCache();
        $this->registerRegistry();
        $this->registerPipeline();
        $this->registerEngine();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/localizer.php' => $this->app->configPath('localizer.php'),
            ], 'localizer-config');

            $this->commands([
                ScanCommand::class,
            ]);
        }
    }

    private function registerSupportServices(): void
    {
        $this->app->singleton(CallExtractor::class);
        $this->app->singleton(StringClassifier::class);
        $this->app->singleton(PathMatcher::class);
        $this->app->singleton(FileHasher::class);
        $this->app->singleton(AtomicWriter::class);
    }

    private function registerCache(): void
    {
        $this->app->singleton(ScanCache::class, function ($app): ScanCache {
            /** @var array{enabled: bool, path: string} $cacheConfig */
            $cacheConfig = $app['config']->get('localizer.cache');

            if (! $cacheConfig['enabled']) {
                return new NullScanCache;
            }

            return new FileScanCache(
                files: $app->make(Filesystem::class),
                writer: $app->make(AtomicWriter::class),
                path: $cacheConfig['path'],
            );
        });
    }

    private function registerRegistry(): void
    {
        $this->app->singleton(ExtractorRegistry::class, function ($app): ExtractorRegistry {
            $registry = new ExtractorRegistry;

            /** @var array<string, class-string<Extractor>> $extractors */
            $extractors = $app['config']->get('localizer.extractors', []);

            foreach ($extractors as $class) {
                $registry->register($app->make($class));
            }

            return $registry;
        });
    }

    private function registerPipeline(): void
    {
        $this->app->singleton(Discoverer::class, fn ($app): Discoverer => new PipelineDiscoverer(
            $app->make(DiscoverFiles::class),
        ));

        $this->app->singleton(ScanPipeline::class, function ($app): ScanPipeline {
            /** @var array{name: string, seconds: int} $lockConfig */
            $lockConfig = $app['config']->get('localizer.lock');

            return new ScanPipeline(
                pipeline: new Pipeline($app),
                events: $app->make(Dispatcher::class),
                cacheFactory: $app->make(CacheFactory::class),
                stages: [
                    DiscoverFiles::class,
                    FilterCached::class,
                    ExtractStrings::class,
                    NormalizeStrings::class,
                    PersistCache::class,
                ],
                lockName: $lockConfig['name'],
                lockSeconds: $lockConfig['seconds'],
            );
        });

        $this->app->bind(NormalizeStrings::class, fn ($app): NormalizeStrings => new NormalizeStrings(
            $app->make(Localizer::class)->normalizers(),
        ));
    }

    private function registerEngine(): void
    {
        $this->app->singleton(Localizer::class, function ($app): Localizer {
            /** @var array{paths: list<string>, exclude: list<string>} $defaults */
            $defaults = [
                'paths' => $app['config']->get('localizer.paths', []),
                'exclude' => $app['config']->get('localizer.exclude', []),
            ];

            return new Localizer(
                pipeline: $app->make(ScanPipeline::class),
                registry: $app->make(ExtractorRegistry::class),
                defaults: $defaults,
            );
        });
    }

    /**
     * @return list<string>
     */
    public function provides(): array
    {
        return [
            Localizer::class,
            ScanPipeline::class,
            ExtractorRegistry::class,
            ScanCache::class,
            Discoverer::class,
        ];
    }
}
