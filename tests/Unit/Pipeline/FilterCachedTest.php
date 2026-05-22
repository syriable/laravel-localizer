<?php

declare(strict_types=1);

use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Syriable\Localizer\Cache\FileScanCache;
use Syriable\Localizer\Cache\NullScanCache;
use Syriable\Localizer\Data\DiscoveredFile;
use Syriable\Localizer\Data\ScanRequest;
use Syriable\Localizer\Events\FileExtracted;
use Syriable\Localizer\Pipeline\FilterCached;
use Syriable\Localizer\Pipeline\ScanPayload;
use Syriable\Localizer\Support\AtomicWriter;
use Syriable\Localizer\Support\FileHasher;

use function Syriable\Localizer\Tests\removeDir;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/filter-cached-'.bin2hex(random_bytes(4));
    mkdir($this->tempDir, 0o755, true);

    $files = new Filesystem;
    $this->cachePath = $this->tempDir.'/cache.json';
    $this->cache = new FileScanCache(
        files: $files,
        writer: new AtomicWriter($files),
        path: $this->cachePath,
    );

    $this->hasher = new FileHasher($files);
    $this->events = new Dispatcher;

    $this->stage = new FilterCached(
        cache: $this->cache,
        hasher: $this->hasher,
        events: $this->events,
    );
});

afterEach(function () {
    removeDir($this->tempDir);
});

function makeDiscoveredFile(string $absolutePath, string $extractor = 'blade'): DiscoveredFile
{
    return new DiscoveredFile(
        absolutePath: $absolutePath,
        relativePath: basename($absolutePath),
        extension: pathinfo($absolutePath, PATHINFO_EXTENSION),
        extractor: $extractor,
        size: filesize($absolutePath) ?: 0,
    );
}

describe('FilterCached', function () {
    it('routes uncached files into freshFiles', function () {
        $path = $this->tempDir.'/a.blade.php';
        file_put_contents($path, 'sample');

        $payload = new ScanPayload(new ScanRequest(paths: [$this->tempDir]));
        $payload->discoveredFiles = [makeDiscoveredFile($path)];

        $this->stage->handle($payload, static fn ($p) => $p);

        expect($payload->freshFiles)->toHaveCount(1)
            ->and($payload->cachedFiles)->toBe([]);
    });

    it('routes cached files into cachedFiles when fingerprint matches', function () {
        $path = $this->tempDir.'/cached.blade.php';
        file_put_contents($path, 'cached contents');

        // Pre-populate cache with the matching fingerprint.
        $fingerprint = $this->hasher->hashFile($path);
        $this->cache->store($path, $fingerprint, [makeExtractedString(value: 'preloaded')]);

        $payload = new ScanPayload(new ScanRequest(paths: [$this->tempDir]));
        $payload->discoveredFiles = [makeDiscoveredFile($path)];

        $this->stage->handle($payload, static fn ($p) => $p);

        expect($payload->cachedFiles)->toHaveCount(1)
            ->and($payload->freshFiles)->toBe([])
            ->and($payload->cachedStrings)->toHaveCount(1)
            ->and($payload->cachedStrings[0]->value)->toBe('preloaded');
    });

    it('routes files into freshFiles when fingerprint changes', function () {
        $path = $this->tempDir.'/changed.blade.php';
        file_put_contents($path, 'original');

        $this->cache->store($path, 'xxh128:stale-fingerprint', [makeExtractedString(value: 'stale')]);

        $payload = new ScanPayload(new ScanRequest(paths: [$this->tempDir]));
        $payload->discoveredFiles = [makeDiscoveredFile($path)];

        $this->stage->handle($payload, static fn ($p) => $p);

        expect($payload->freshFiles)->toHaveCount(1)
            ->and($payload->cachedFiles)->toBe([])
            ->and($payload->cachedStrings)->toBe([]);
    });

    it('skips the cache entirely when useCache is false', function () {
        $path = $this->tempDir.'/whatever.blade.php';
        file_put_contents($path, 'contents');

        $fingerprint = $this->hasher->hashFile($path);
        $this->cache->store($path, $fingerprint, [makeExtractedString(value: 'wouldve been used')]);

        $payload = new ScanPayload(new ScanRequest(paths: [$this->tempDir], useCache: false));
        $payload->discoveredFiles = [makeDiscoveredFile($path)];

        $this->stage->handle($payload, static fn ($p) => $p);

        expect($payload->freshFiles)->toHaveCount(1)
            ->and($payload->cachedFiles)->toBe([]);
    });

    it('records fingerprints into the payload', function () {
        $path = $this->tempDir.'/fp.blade.php';
        file_put_contents($path, 'hashable');

        $payload = new ScanPayload(new ScanRequest(paths: [$this->tempDir]));
        $payload->discoveredFiles = [makeDiscoveredFile($path)];

        $this->stage->handle($payload, static fn ($p) => $p);

        expect($payload->fingerprints)->toHaveKey($path)
            ->and($payload->fingerprints[$path])->toStartWith('xxh128:');
    });

    it('fires FileExtracted with fromCache=true for cache hits', function () {
        $path = $this->tempDir.'/hit.blade.php';
        file_put_contents($path, 'hit');

        $fingerprint = $this->hasher->hashFile($path);
        $this->cache->store($path, $fingerprint, [makeExtractedString(value: 'cached')]);

        $captured = null;
        $this->events->listen(FileExtracted::class, function (FileExtracted $event) use (&$captured): void {
            $captured = $event;
        });

        $payload = new ScanPayload(new ScanRequest(paths: [$this->tempDir]));
        $payload->discoveredFiles = [makeDiscoveredFile($path)];

        $this->stage->handle($payload, static fn ($p) => $p);

        expect($captured)->not->toBeNull()
            ->and($captured->fromCache)->toBeTrue()
            ->and($captured->strings)->toHaveCount(1);
    });

    it('does not fire FileExtracted for fresh files', function () {
        $path = $this->tempDir.'/fresh.blade.php';
        file_put_contents($path, 'fresh');

        $fired = false;
        $this->events->listen(FileExtracted::class, function () use (&$fired): void {
            $fired = true;
        });

        $payload = new ScanPayload(new ScanRequest(paths: [$this->tempDir]));
        $payload->discoveredFiles = [makeDiscoveredFile($path)];

        $this->stage->handle($payload, static fn ($p) => $p);

        expect($fired)->toBeFalse();
    });

    it('with NullScanCache routes everything as fresh', function () {
        $path = $this->tempDir.'/file.blade.php';
        file_put_contents($path, 'x');

        $stage = new FilterCached(
            cache: new NullScanCache,
            hasher: $this->hasher,
            events: $this->events,
        );

        $payload = new ScanPayload(new ScanRequest(paths: [$this->tempDir]));
        $payload->discoveredFiles = [makeDiscoveredFile($path)];

        $stage->handle($payload, static fn ($p) => $p);

        expect($payload->freshFiles)->toHaveCount(1)
            ->and($payload->cachedFiles)->toBe([]);
    });

    it('calls the next stage', function () {
        $called = false;
        $next = function ($p) use (&$called) {
            $called = true;

            return $p;
        };

        $payload = new ScanPayload(new ScanRequest(paths: ['/x']));
        $this->stage->handle($payload, $next);

        expect($called)->toBeTrue();
    });
});
