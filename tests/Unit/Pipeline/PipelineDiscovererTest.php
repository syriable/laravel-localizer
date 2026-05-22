<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Syriable\Localizer\Data\ScanRequest;
use Syriable\Localizer\Extractors\BladeExtractor;
use Syriable\Localizer\Pipeline\DiscoverFiles;
use Syriable\Localizer\Pipeline\PipelineDiscoverer;
use Syriable\Localizer\Support\CallExtractor;
use Syriable\Localizer\Support\ExtractorRegistry;
use Syriable\Localizer\Support\PathMatcher;
use Syriable\Localizer\Support\StringClassifier;

use function Syriable\Localizer\Tests\removeDir;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/pipeline-discoverer-'.bin2hex(random_bytes(4));
    mkdir($this->tempDir, 0o755, true);

    $registry = new ExtractorRegistry;
    $registry->register(new BladeExtractor(new CallExtractor, new StringClassifier));

    $stage = new DiscoverFiles(
        files: new Filesystem,
        registry: $registry,
        matcher: new PathMatcher,
    );

    $this->discoverer = new PipelineDiscoverer($stage);
});

afterEach(function () {
    removeDir($this->tempDir);
});

describe('PipelineDiscoverer', function () {
    it('returns the discovered files for a request', function () {
        $path = $this->tempDir.'/welcome.blade.php';
        file_put_contents($path, '');

        $files = $this->discoverer->discover(new ScanRequest(paths: [$this->tempDir]));

        $list = is_array($files) ? $files : iterator_to_array($files);

        // Symfony Finder resolves symlinks (e.g. macOS /var → /private/var),
        // so compare against realpath() of the path we wrote.
        expect($list)->toHaveCount(1)
            ->and($list[0]->absolutePath)->toBe(realpath($path));
    });

    it('returns nothing for an empty directory', function () {
        $files = $this->discoverer->discover(new ScanRequest(paths: [$this->tempDir]));
        $list = is_array($files) ? $files : iterator_to_array($files);

        expect($list)->toBe([]);
    });
});
