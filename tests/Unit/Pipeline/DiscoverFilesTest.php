<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Syriable\Localizer\Data\ScanRequest;
use Syriable\Localizer\Extractors\BladeExtractor;
use Syriable\Localizer\Extractors\PhpExtractor;
use Syriable\Localizer\Extractors\VueExtractor;
use Syriable\Localizer\Pipeline\DiscoverFiles;
use Syriable\Localizer\Pipeline\ScanPayload;
use Syriable\Localizer\Support\CallExtractor;
use Syriable\Localizer\Support\ExtractorRegistry;
use Syriable\Localizer\Support\PathMatcher;
use Syriable\Localizer\Support\StringClassifier;

use function Syriable\Localizer\Tests\removeDir;
use function Syriable\Localizer\Tests\writeFile;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/discover-test-'.bin2hex(random_bytes(4));
    mkdir($this->tempDir, 0o755, true);

    $calls = new CallExtractor;
    $classifier = new StringClassifier;

    $registry = new ExtractorRegistry;
    $registry->register(new BladeExtractor($calls, $classifier));
    $registry->register(new PhpExtractor($calls, $classifier));
    $registry->register(new VueExtractor($calls, $classifier));

    $this->stage = new DiscoverFiles(
        files: new Filesystem,
        registry: $registry,
        matcher: new PathMatcher,
    );
});

afterEach(function () {
    removeDir($this->tempDir);
});

describe('DiscoverFiles', function () {
    it('discovers a single file by extractor pattern', function () {
        writeFile($this->tempDir, 'welcome.blade.php');

        $payload = new ScanPayload(new ScanRequest(paths: [$this->tempDir]));
        $this->stage->handle($payload, static fn ($p) => $p);

        expect($payload->discoveredFiles)->toHaveCount(1)
            ->and($payload->discoveredFiles[0]->extractor)->toBe('blade');
    });

    it('discovers multiple files across nested directories', function () {
        writeFile($this->tempDir, 'a/welcome.blade.php');
        writeFile($this->tempDir, 'b/User.php');
        writeFile($this->tempDir, 'c/Dashboard.vue');

        $payload = new ScanPayload(new ScanRequest(paths: [$this->tempDir]));
        $this->stage->handle($payload, static fn ($p) => $p);

        expect($payload->discoveredFiles)->toHaveCount(3);
    });

    it('returns files in sorted absolute-path order', function () {
        writeFile($this->tempDir, 'z.blade.php');
        writeFile($this->tempDir, 'a.blade.php');
        writeFile($this->tempDir, 'm.blade.php');

        $payload = new ScanPayload(new ScanRequest(paths: [$this->tempDir]));
        $this->stage->handle($payload, static fn ($p) => $p);

        $paths = array_map(static fn ($f) => $f->absolutePath, $payload->discoveredFiles);
        $sorted = $paths;
        sort($sorted);

        expect($paths)->toBe($sorted);
    });

    it('respects exclusion patterns', function () {
        writeFile($this->tempDir, 'keep/welcome.blade.php');
        writeFile($this->tempDir, 'node_modules/lib.blade.php');

        $payload = new ScanPayload(new ScanRequest(
            paths: [$this->tempDir],
            exclude: ['**/node_modules/**'],
        ));

        $this->stage->handle($payload, static fn ($p) => $p);

        expect($payload->discoveredFiles)->toHaveCount(1)
            ->and(str_contains($payload->discoveredFiles[0]->absolutePath, 'node_modules'))->toBeFalse();
    });

    it('skips files with no matching extractor', function () {
        writeFile($this->tempDir, 'image.png');
        writeFile($this->tempDir, 'README.md');
        writeFile($this->tempDir, 'welcome.blade.php');

        $payload = new ScanPayload(new ScanRequest(paths: [$this->tempDir]));
        $this->stage->handle($payload, static fn ($p) => $p);

        expect($payload->discoveredFiles)->toHaveCount(1);
    });

    it('silently ignores non-existing paths', function () {
        writeFile($this->tempDir, 'welcome.blade.php');

        $payload = new ScanPayload(new ScanRequest(
            paths: [$this->tempDir, '/totally/nonexistent/path'],
        ));

        $this->stage->handle($payload, static fn ($p) => $p);

        expect($payload->discoveredFiles)->toHaveCount(1);
    });

    it('accepts single-file paths', function () {
        $file = writeFile($this->tempDir, 'one.blade.php');

        $payload = new ScanPayload(new ScanRequest(paths: [$file]));
        $this->stage->handle($payload, static fn ($p) => $p);

        expect($payload->discoveredFiles)->toHaveCount(1);
    });

    it('respects the extractors restriction from the request', function () {
        writeFile($this->tempDir, 'welcome.blade.php');
        writeFile($this->tempDir, 'Dashboard.vue');

        $payload = new ScanPayload(new ScanRequest(
            paths: [$this->tempDir],
            extractors: ['vue'],
        ));

        $this->stage->handle($payload, static fn ($p) => $p);

        expect($payload->discoveredFiles)->toHaveCount(1)
            ->and($payload->discoveredFiles[0]->extractor)->toBe('vue');
    });

    it('resolves the first-matching extractor when patterns overlap', function () {
        writeFile($this->tempDir, 'welcome.blade.php');

        $payload = new ScanPayload(new ScanRequest(paths: [$this->tempDir]));
        $this->stage->handle($payload, static fn ($p) => $p);

        // BladeExtractor registered first; .blade.php must match blade, not php.
        expect($payload->discoveredFiles[0]->extractor)->toBe('blade');
    });

    it('captures the file size', function () {
        writeFile($this->tempDir, 'sample.blade.php', 'hello world');

        $payload = new ScanPayload(new ScanRequest(paths: [$this->tempDir]));
        $this->stage->handle($payload, static fn ($p) => $p);

        expect($payload->discoveredFiles[0]->size)->toBe(11);
    });

    it('computes a relative path from the scan root', function () {
        writeFile($this->tempDir, 'nested/sample.blade.php');

        $payload = new ScanPayload(new ScanRequest(paths: [$this->tempDir]));
        $this->stage->handle($payload, static fn ($p) => $p);

        expect($payload->discoveredFiles[0]->relativePath)->toBe('nested/sample.blade.php');
    });

    it('normalises the relative path to forward slashes regardless of OS', function () {
        // On Windows, realpath()/Finder yield backslash separators. The
        // relativePath field must always be forward-slash separated so it
        // is stable across platforms.
        writeFile($this->tempDir, 'deep/nested/dir/sample.blade.php');

        $payload = new ScanPayload(new ScanRequest(paths: [$this->tempDir]));
        $this->stage->handle($payload, static fn ($p) => $p);

        $relativePath = $payload->discoveredFiles[0]->relativePath;

        expect($relativePath)->toBe('deep/nested/dir/sample.blade.php')
            ->and($relativePath)->not->toContain('\\');
    });

    it('deduplicates files discovered through multiple overlapping paths', function () {
        writeFile($this->tempDir, 'shared.blade.php');

        $payload = new ScanPayload(new ScanRequest(
            paths: [$this->tempDir, $this->tempDir],
        ));

        $this->stage->handle($payload, static fn ($p) => $p);

        expect($payload->discoveredFiles)->toHaveCount(1);
    });

    it('calls the next stage', function () {
        $called = false;
        $next = function ($p) use (&$called) {
            $called = true;

            return $p;
        };

        $payload = new ScanPayload(new ScanRequest(paths: [$this->tempDir]));
        $this->stage->handle($payload, $next);

        expect($called)->toBeTrue();
    });
});
