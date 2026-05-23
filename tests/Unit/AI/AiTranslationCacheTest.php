<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Syriable\Localizer\AI\AiTranslationCache;
use Syriable\Localizer\Support\AtomicWriter;

describe('AiTranslationCache', function () {
    beforeEach(function () {
        $this->dir = sys_get_temp_dir().'/ai-cache-test-'.bin2hex(random_bytes(4));
        mkdir($this->dir, 0o755, true);
        $this->cachePath = $this->dir.'/ai-cache.json';

        $this->cache = new AiTranslationCache(
            files: new Filesystem,
            writer: new AtomicWriter(new Filesystem),
            path: $this->cachePath,
        );
    });

    afterEach(function () {
        Syriable\Localizer\Tests\removeDir($this->dir);
    });

    it('returns null for a missing key', function () {
        expect($this->cache->get('nonexistent'))->toBeNull();
    });

    it('returns null when the cache file does not exist', function () {
        expect(file_exists($this->cachePath))->toBeFalse();
        expect($this->cache->get('any'))->toBeNull();
    });

    it('stores and retrieves a value', function () {
        $this->cache->set('key1', 'Bonjour');

        expect($this->cache->get('key1'))->toBe('Bonjour');
    });

    it('preserves existing entries when adding a new one', function () {
        $this->cache->set('a', 'Alpha');
        $this->cache->set('b', 'Beta');

        expect($this->cache->get('a'))->toBe('Alpha');
        expect($this->cache->get('b'))->toBe('Beta');
    });

    it('makeKey is stable for the same inputs', function () {
        $k1 = $this->cache->makeKey('claude-opus-4-7', 'en', 'fr', 'Hello');
        $k2 = $this->cache->makeKey('claude-opus-4-7', 'en', 'fr', 'Hello');

        expect($k1)->toBe($k2);
    });

    it('makeKey differs when the model changes', function () {
        $k1 = $this->cache->makeKey('claude-opus-4-7', 'en', 'fr', 'Hello');
        $k2 = $this->cache->makeKey('claude-haiku-4-5', 'en', 'fr', 'Hello');

        expect($k1)->not->toBe($k2);
    });

    it('makeKey differs when the target locale changes', function () {
        $k1 = $this->cache->makeKey('m', 'en', 'fr', 'Hello');
        $k2 = $this->cache->makeKey('m', 'en', 'de', 'Hello');

        expect($k1)->not->toBe($k2);
    });

    it('makeKey differs when the text changes', function () {
        $k1 = $this->cache->makeKey('m', 'en', 'fr', 'Hello');
        $k2 = $this->cache->makeKey('m', 'en', 'fr', 'Goodbye');

        expect($k1)->not->toBe($k2);
    });

    it('returns null and does not crash on a corrupt cache file', function () {
        file_put_contents($this->cachePath, 'not valid json {{{}}}');

        expect($this->cache->get('any'))->toBeNull();
    });

    it('creates parent directories if they do not exist', function () {
        $deepPath = $this->dir.'/a/b/c/ai-cache.json';
        $cache = new AiTranslationCache(
            files: new Filesystem,
            writer: new AtomicWriter(new Filesystem),
            path: $deepPath,
        );

        $cache->set('hello', 'world');

        expect(file_exists($deepPath))->toBeTrue();
        expect($cache->get('hello'))->toBe('world');
    });
});
