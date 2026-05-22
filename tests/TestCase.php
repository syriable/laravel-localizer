<?php

declare(strict_types=1);

namespace Syriable\Localizer\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Syriable\Localizer\Facades\Localizer;
use Syriable\Localizer\LocalizerServiceProvider;

/**
 * Base test case for the package.
 *
 * Boots a minimal Laravel application via Orchestra Testbench, registers
 * the package's service provider, and points the cache file at a
 * disposable temporary location so each test starts with clean state.
 *
 * Lifecycle note: `$tempPath` is assigned inside {@see defineEnvironment()},
 * not {@see setUp()}. Testbench invokes `defineEnvironment()` during
 * `parent::setUp()` before our `setUp()` body runs, so any assignment in
 * `setUp()` would happen too late for the config to use it.
 */
abstract class TestCase extends Orchestra
{
    protected string $tempPath;

    protected function tearDown(): void
    {
        if (isset($this->tempPath)) {
            $this->removeDirectory($this->tempPath);
        }

        parent::tearDown();
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [LocalizerServiceProvider::class];
    }

    /**
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return ['Localizer' => Localizer::class];
    }

    protected function defineEnvironment($app): void
    {
        $this->tempPath = sys_get_temp_dir().'/localizer-test-'.bin2hex(random_bytes(6));
        @mkdir($this->tempPath, 0o755, true);

        $app['config']->set('localizer.paths', [$this->tempPath]);
        $app['config']->set('localizer.exclude', []);
        $app['config']->set('localizer.cache.enabled', true);
        $app['config']->set('localizer.cache.path', $this->tempPath.'/localizer-cache.json');
        $app['config']->set('localizer.lock.name', 'localizer:test:'.bin2hex(random_bytes(4)));
        $app['config']->set('localizer.lock.seconds', 5);
        $app['config']->set('cache.default', 'array');
    }

    /**
     * Writes a fixture file under the temp path and returns its absolute path.
     */
    protected function writeFixture(string $relativePath, string $contents): string
    {
        $absolute = $this->tempPath.'/'.ltrim($relativePath, '/');
        $directory = \dirname($absolute);

        if (! is_dir($directory)) {
            mkdir($directory, 0o755, recursive: true);
        }

        file_put_contents($absolute, $contents);

        return $absolute;
    }

    /**
     * Copies a bundled fixture file into the temp path.
     */
    protected function copyFixture(string $relativeSource, string $relativeDest): string
    {
        $source = __DIR__.'/Fixtures/'.ltrim($relativeSource, '/');
        $contents = file_get_contents($source);

        if ($contents === false) {
            throw new \RuntimeException("Failed to read fixture: {$source}");
        }

        return $this->writeFixture($relativeDest, $contents);
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = scandir($path);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path.'/'.$item;

            if (is_link($full)) {
                if (file_exists($full) || is_link($full)) {
                    unlink($full);
                }

                continue;
            }

            if (is_dir($full)) {
                $this->removeDirectory($full);

                continue;
            }

            if (file_exists($full)) {
                unlink($full);
            }
        }

        if (is_dir($path)) {
            $remaining = scandir($path);
            if ($remaining !== false && count(array_diff($remaining, ['.', '..'])) === 0) {
                rmdir($path);
            }
        }
    }
}
