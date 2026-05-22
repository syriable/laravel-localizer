<?php

declare(strict_types=1);

use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Syriable\Localizer\Cache\FileScanCache;
use Syriable\Localizer\Data\DiscoveredFile;
use Syriable\Localizer\Data\ScanRequest;
use Syriable\Localizer\Events\FileExtracted;
use Syriable\Localizer\Extractors\BladeExtractor;
use Syriable\Localizer\Extractors\PhpExtractor;
use Syriable\Localizer\Pipeline\ExtractStrings;
use Syriable\Localizer\Pipeline\ScanPayload;
use Syriable\Localizer\Support\AtomicWriter;
use Syriable\Localizer\Support\CallExtractor;
use Syriable\Localizer\Support\ExtractorRegistry;
use Syriable\Localizer\Support\StringClassifier;

use function Syriable\Localizer\Tests\removeDir;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/extract-strings-'.bin2hex(random_bytes(4));
    mkdir($this->tempDir, 0o755, true);

    $files = new Filesystem;
    $calls = new CallExtractor;
    $classifier = new StringClassifier;

    $registry = new ExtractorRegistry;
    $registry->register(new BladeExtractor($calls, $classifier));
    $registry->register(new PhpExtractor($calls, $classifier));

    $this->cachePath = $this->tempDir.'/cache.json';
    $this->cache = new FileScanCache(
        files: $files,
        writer: new AtomicWriter($files),
        path: $this->cachePath,
    );

    $this->events = new Dispatcher;

    $this->stage = new ExtractStrings(
        files: $files,
        registry: $registry,
        cache: $this->cache,
        events: $this->events,
    );
});

afterEach(function () {
    removeDir($this->tempDir);
});

function discoveredBladeFile(string $absolutePath): DiscoveredFile
{
    return new DiscoveredFile(
        absolutePath: $absolutePath,
        relativePath: basename($absolutePath),
        extension: 'php',
        extractor: 'blade',
        size: filesize($absolutePath) ?: 0,
    );
}

describe('ExtractStrings', function () {
    it('extracts strings from fresh files', function () {
        $path = $this->tempDir.'/welcome.blade.php';
        file_put_contents($path, "{{ __('Hello world') }}");

        $payload = new ScanPayload(new ScanRequest(paths: [$this->tempDir]));
        $payload->freshFiles = [discoveredBladeFile($path)];
        $payload->fingerprints[$path] = 'xxh128:abc';

        $this->stage->handle($payload, static fn ($p) => $p);

        expect($payload->freshStrings)->toHaveCount(1)
            ->and($payload->freshStrings[0]->value)->toBe('Hello world');
    });

    it('extracts strings from multiple fresh files', function () {
        $a = $this->tempDir.'/a.blade.php';
        $b = $this->tempDir.'/b.blade.php';
        file_put_contents($a, "@lang('first')");
        file_put_contents($b, "@lang('second')");

        $payload = new ScanPayload(new ScanRequest(paths: [$this->tempDir]));
        $payload->freshFiles = [discoveredBladeFile($a), discoveredBladeFile($b)];
        $payload->fingerprints[$a] = 'xxh128:a';
        $payload->fingerprints[$b] = 'xxh128:b';

        $this->stage->handle($payload, static fn ($p) => $p);

        expect($payload->freshStrings)->toHaveCount(2);
    });

    it('stores fresh extractions in the cache when useCache is true', function () {
        $path = $this->tempDir.'/cached.blade.php';
        file_put_contents($path, "__('cached value')");

        $payload = new ScanPayload(new ScanRequest(paths: [$this->tempDir], useCache: true));
        $payload->freshFiles = [discoveredBladeFile($path)];
        $payload->fingerprints[$path] = 'xxh128:fingerprint';

        $this->stage->handle($payload, static fn ($p) => $p);

        expect($this->cache->fingerprint($path))->toBe('xxh128:fingerprint')
            ->and($this->cache->load($path)[0]->value)->toBe('cached value');
    });

    it('does not store extractions in the cache when useCache is false', function () {
        $path = $this->tempDir.'/skip-cache.blade.php';
        file_put_contents($path, "__('skip')");

        $payload = new ScanPayload(new ScanRequest(paths: [$this->tempDir], useCache: false));
        $payload->freshFiles = [discoveredBladeFile($path)];
        $payload->fingerprints[$path] = 'xxh128:skip';

        $this->stage->handle($payload, static fn ($p) => $p);

        expect($this->cache->fingerprint($path))->toBeNull();
    });

    it('fires FileExtracted with fromCache=false for each fresh file', function () {
        $path = $this->tempDir.'/announce.blade.php';
        file_put_contents($path, "__('announce me')");

        $captured = [];
        $this->events->listen(FileExtracted::class, function (FileExtracted $event) use (&$captured): void {
            $captured[] = $event;
        });

        $payload = new ScanPayload(new ScanRequest(paths: [$this->tempDir]));
        $payload->freshFiles = [discoveredBladeFile($path)];
        $payload->fingerprints[$path] = 'xxh128:announce';

        $this->stage->handle($payload, static fn ($p) => $p);

        expect($captured)->toHaveCount(1)
            ->and($captured[0]->fromCache)->toBeFalse()
            ->and($captured[0]->strings)->toHaveCount(1);
    });

    it('skips files that no longer exist on disk', function () {
        $path = $this->tempDir.'/will-be-deleted.blade.php';
        file_put_contents($path, "__('orig')");
        $file = discoveredBladeFile($path);
        unlink($path);

        $payload = new ScanPayload(new ScanRequest(paths: [$this->tempDir]));
        $payload->freshFiles = [$file];
        $payload->fingerprints[$path] = 'xxh128:gone';

        $this->stage->handle($payload, static fn ($p) => $p);

        expect($payload->freshStrings)->toBe([]);
    });

    it('does nothing when there are no fresh files', function () {
        $payload = new ScanPayload(new ScanRequest(paths: [$this->tempDir]));

        $this->stage->handle($payload, static fn ($p) => $p);

        expect($payload->freshStrings)->toBe([]);
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
