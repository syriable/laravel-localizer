<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Syriable\Localizer\Cache\FileScanCache;
use Syriable\Localizer\Data\ExtractedString;
use Syriable\Localizer\Data\SourceLocation;
use Syriable\Localizer\Data\StringKind;
use Syriable\Localizer\Exceptions\LocalizerException;
use Syriable\Localizer\Support\AtomicWriter;

use function Syriable\Localizer\Tests\removeDir;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/file-cache-test-'.bin2hex(random_bytes(4));
    mkdir($this->tempDir, 0o755, true);
    $this->cachePath = $this->tempDir.'/cache.json';

    $files = new Filesystem;
    $this->cache = new FileScanCache(
        files: $files,
        writer: new AtomicWriter($files),
        path: $this->cachePath,
    );
});

afterEach(function () {
    removeDir($this->tempDir);
});

function makeCacheableString(string $value): ExtractedString
{
    return new ExtractedString(
        value: $value,
        kind: StringKind::JsonKey,
        extractor: 'test',
        location: new SourceLocation('/abs/file.php', 1),
    );
}

describe('FileScanCache', function () {
    it('returns null for an unknown path', function () {
        expect($this->cache->fingerprint('/missing.php'))->toBeNull()
            ->and($this->cache->load('/missing.php'))->toBe([]);
    });

    it('stores and retrieves a fingerprint', function () {
        $this->cache->store('/file.php', 'fp123', [makeCacheableString('hello')]);

        expect($this->cache->fingerprint('/file.php'))->toBe('fp123');
    });

    it('stores and retrieves strings', function () {
        $a = makeCacheableString('one');
        $b = makeCacheableString('two');

        $this->cache->store('/file.php', 'fp', [$a, $b]);
        $loaded = $this->cache->load('/file.php');

        expect($loaded)->toHaveCount(2)
            ->and($loaded[0]->value)->toBe('one')
            ->and($loaded[1]->value)->toBe('two');
    });

    it('replaces previously-stored entry for the same path', function () {
        $this->cache->store('/file.php', 'old', [makeCacheableString('old')]);
        $this->cache->store('/file.php', 'new', [makeCacheableString('new')]);

        expect($this->cache->fingerprint('/file.php'))->toBe('new')
            ->and($this->cache->load('/file.php')[0]->value)->toBe('new');
    });

    it('forgets a single entry', function () {
        $this->cache->store('/a.php', 'fp', [makeCacheableString('a')]);
        $this->cache->store('/b.php', 'fp', [makeCacheableString('b')]);

        $this->cache->forget('/a.php');

        expect($this->cache->fingerprint('/a.php'))->toBeNull()
            ->and($this->cache->fingerprint('/b.php'))->toBe('fp');
    });

    it('forget() on a missing path is a no-op', function () {
        $this->cache->forget('/never-stored');

        expect(true)->toBeTrue();
    });

    it('flushes all entries and writes the empty state to disk', function () {
        $this->cache->store('/a.php', 'fp', [makeCacheableString('a')]);
        $this->cache->flush();

        expect($this->cache->count())->toBe(0)
            ->and(file_exists($this->cachePath))->toBeTrue();
    });

    it('commit() writes the cache file', function () {
        $this->cache->store('/a.php', 'fp', [makeCacheableString('a')]);
        $this->cache->commit();

        $raw = file_get_contents($this->cachePath);
        $decoded = json_decode($raw, true);

        expect($decoded)->toHaveKey('version')
            ->and($decoded)->toHaveKey('entries')
            ->and($decoded['entries'])->toHaveKey('/a.php');
    });

    it('commit() is a no-op when no changes were made', function () {
        $this->cache->commit();

        expect(file_exists($this->cachePath))->toBeFalse();
    });

    it('loads existing cache file on first access', function () {
        // Pre-write a cache file using the current schema version.
        $payload = [
            'version' => 2,
            'entries' => [
                '/preexisting.php' => [
                    'fingerprint' => 'preFp',
                    'strings' => [makeCacheableString('preloaded')->toArray()],
                ],
            ],
        ];
        file_put_contents($this->cachePath, json_encode($payload));

        // New cache instance reads it lazily.
        $files = new Filesystem;
        $cache = new FileScanCache(
            files: $files,
            writer: new AtomicWriter($files),
            path: $this->cachePath,
        );

        expect($cache->fingerprint('/preexisting.php'))->toBe('preFp')
            ->and($cache->load('/preexisting.php')[0]->value)->toBe('preloaded');
    });

    it('treats a cache file with unknown version as empty', function () {
        file_put_contents($this->cachePath, json_encode(['version' => 999, 'entries' => []]));

        $files = new Filesystem;
        $cache = new FileScanCache(
            files: $files,
            writer: new AtomicWriter($files),
            path: $this->cachePath,
        );

        expect($cache->count())->toBe(0);
    });

    it('rejects pre-1.0 (v1) caches', function () {
        // v1 documents stored {group, namespace, …}; we cannot safely
        // load them into the new {package, directories, file, key} shape,
        // so the load path treats them as a miss across the board.
        file_put_contents($this->cachePath, json_encode([
            'version' => 1,
            'entries' => [
                '/legacy.php' => [
                    'fingerprint' => 'oldFp',
                    'strings' => [['value' => 'x', 'kind' => 'json_key', 'extractor' => 'php', 'location' => ['path' => '/legacy.php', 'line' => 1]]],
                ],
            ],
        ]));

        $files = new Filesystem;
        $cache = new FileScanCache(
            files: $files,
            writer: new AtomicWriter($files),
            path: $this->cachePath,
        );

        expect($cache->count())->toBe(0)
            ->and($cache->fingerprint('/legacy.php'))->toBeNull();
    });

    it('treats a corrupt cache file as empty without throwing', function () {
        file_put_contents($this->cachePath, '{not valid json');

        $files = new Filesystem;
        $cache = new FileScanCache(
            files: $files,
            writer: new AtomicWriter($files),
            path: $this->cachePath,
        );

        expect($cache->count())->toBe(0);
    });

    it('assertReadable() throws on corrupt cache', function () {
        file_put_contents($this->cachePath, '{not valid');

        $files = new Filesystem;
        $cache = new FileScanCache(
            files: $files,
            writer: new AtomicWriter($files),
            path: $this->cachePath,
        );

        $cache->assertReadable();
    })->throws(LocalizerException::class, 'corrupt');

    it('preserves round-trip integrity through commit + reload', function () {
        $original = new ExtractedString(
            value: 'pagination.next',
            kind: StringKind::ShortKey,
            extractor: 'blade',
            location: new SourceLocation('/abs.blade.php', 5, 3),
            file: 'pagination',
            key: 'next',
        );

        $this->cache->store('/file.blade.php', 'fp', [$original]);
        $this->cache->commit();

        $files = new Filesystem;
        $fresh = new FileScanCache(
            files: $files,
            writer: new AtomicWriter($files),
            path: $this->cachePath,
        );

        $loaded = $fresh->load('/file.blade.php');

        expect($loaded[0]->toArray())->toBe($original->toArray());
    });

    it('exposes the cache path', function () {
        expect($this->cache->path())->toBe($this->cachePath);
    });

    describe('prune()', function () {
        it('removes entries whose paths are not in the known set', function () {
            $this->cache->store('/a.php', 'fp', [makeCacheableString('a')]);
            $this->cache->store('/b.php', 'fp', [makeCacheableString('b')]);
            $this->cache->store('/deleted.php', 'fp', [makeCacheableString('gone')]);

            $this->cache->prune(['/a.php', '/b.php']);

            expect($this->cache->fingerprint('/a.php'))->toBe('fp')
                ->and($this->cache->fingerprint('/b.php'))->toBe('fp')
                ->and($this->cache->fingerprint('/deleted.php'))->toBeNull()
                ->and($this->cache->count())->toBe(2);
        });

        it('marks the cache dirty so the pruned state is persisted on commit()', function () {
            $this->cache->store('/gone.php', 'fp', [makeCacheableString('gone')]);
            $this->cache->commit(); // write initial state

            // A new cache instance reads the file.
            $files = new Filesystem;
            $cache2 = new FileScanCache(
                files: $files,
                writer: new AtomicWriter($files),
                path: $this->cachePath,
            );

            $cache2->prune([]); // prune all
            $cache2->commit();

            // Reload and verify the stale entry is gone.
            $files2 = new Filesystem;
            $cache3 = new FileScanCache(
                files: $files2,
                writer: new AtomicWriter($files2),
                path: $this->cachePath,
            );

            expect($cache3->count())->toBe(0);
        });

        it('is a no-op when all paths are still known', function () {
            $this->cache->store('/a.php', 'fp', [makeCacheableString('a')]);
            $this->cache->commit();

            $countBefore = $this->cache->count();
            $this->cache->prune(['/a.php']);

            expect($this->cache->count())->toBe($countBefore);
        });

        it('with an empty known set removes all entries', function () {
            $this->cache->store('/a.php', 'fp', [makeCacheableString('a')]);
            $this->cache->store('/b.php', 'fp', [makeCacheableString('b')]);

            $this->cache->prune([]);

            expect($this->cache->count())->toBe(0);
        });
    });
});
