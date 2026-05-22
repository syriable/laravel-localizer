<?php

declare(strict_types=1);

use Syriable\Localizer\Cache\NullScanCache;

beforeEach(function () {
    $this->cache = new NullScanCache;
});

describe('NullScanCache', function () {
    it('always returns null for fingerprint', function () {
        expect($this->cache->fingerprint('/any/path'))->toBeNull();
    });

    it('always returns an empty array for load', function () {
        expect($this->cache->load('/any/path'))->toBe([]);
    });

    it('store, forget, prune, flush, and commit are no-ops', function () {
        $this->cache->store('/x', 'fp', []);
        $this->cache->forget('/x');
        $this->cache->prune([]);
        $this->cache->flush();
        $this->cache->commit();

        expect($this->cache->fingerprint('/x'))->toBeNull();
    });
});
