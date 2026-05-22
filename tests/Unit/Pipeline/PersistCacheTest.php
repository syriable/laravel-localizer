<?php

declare(strict_types=1);

use Syriable\Localizer\Contracts\ScanCache;
use Syriable\Localizer\Data\ScanRequest;
use Syriable\Localizer\Pipeline\PersistCache;
use Syriable\Localizer\Pipeline\ScanPayload;

/**
 * Test double that counts commit() calls.
 */
final class CommitCountingCache implements ScanCache
{
    public int $commits = 0;

    public function fingerprint(string $absolutePath): ?string
    {
        return null;
    }

    public function load(string $absolutePath): array
    {
        return [];
    }

    public function store(string $absolutePath, string $fingerprint, iterable $strings): void {}

    public function forget(string $absolutePath): void {}

    public function flush(): void {}

    public function commit(): void
    {
        $this->commits++;
    }
}

describe('PersistCache', function () {
    it('commits the cache when useCache is true', function () {
        $cache = new CommitCountingCache;
        $stage = new PersistCache($cache);

        $payload = new ScanPayload(new ScanRequest(paths: ['/x'], useCache: true));
        $stage->handle($payload, static fn ($p) => $p);

        expect($cache->commits)->toBe(1);
    });

    it('does not commit when useCache is false', function () {
        $cache = new CommitCountingCache;
        $stage = new PersistCache($cache);

        $payload = new ScanPayload(new ScanRequest(paths: ['/x'], useCache: false));
        $stage->handle($payload, static fn ($p) => $p);

        expect($cache->commits)->toBe(0);
    });

    it('calls the next stage', function () {
        $stage = new PersistCache(new CommitCountingCache);

        $called = false;
        $stage->handle(
            new ScanPayload(new ScanRequest(paths: ['/x'])),
            function ($p) use (&$called) {
                $called = true;

                return $p;
            },
        );

        expect($called)->toBeTrue();
    });
});
