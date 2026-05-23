<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Syriable\Localizer\Generator\TranslationJsonRepository;
use Syriable\Localizer\Support\AtomicWriter;

use function Syriable\Localizer\Tests\removeDir;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/json-repo-test-'.bin2hex(random_bytes(4));
    mkdir($this->tempDir, 0o755, true);

    $files = new Filesystem;
    $this->repo = new TranslationJsonRepository(
        files: $files,
        writer: new AtomicWriter($files),
    );
});

afterEach(function () {
    removeDir($this->tempDir);
});

describe('TranslationJsonRepository', function () {
    it('returns an empty array when the file does not exist', function () {
        $path = $this->tempDir.'/missing.json';

        expect($this->repo->read($path))->toBe([]);
    });

    it('reads and parses an existing JSON file', function () {
        $path = $this->tempDir.'/en.json';
        file_put_contents($path, json_encode(['Hello' => 'Hello', 'Goodbye' => 'Auf Wiedersehen']));

        $data = $this->repo->read($path);

        expect($data)->toBe(['Hello' => 'Hello', 'Goodbye' => 'Auf Wiedersehen']);
    });

    it('returns an empty array for invalid JSON', function () {
        $path = $this->tempDir.'/broken.json';
        file_put_contents($path, 'not valid json {{{{');

        expect($this->repo->read($path))->toBe([]);
    });

    it('writes a JSON file with pretty-printed content', function () {
        $path = $this->tempDir.'/en.json';
        $this->repo->write($path, ['Welcome' => 'Welcome', 'Goodbye' => 'Goodbye']);

        expect(file_exists($path))->toBeTrue();

        $content = file_get_contents($path);
        expect($content)->toContain('"Welcome"')
            ->and($content)->toContain('"Goodbye"');

        // Confirm valid JSON
        $decoded = json_decode($content, true);
        expect($decoded)->toBe(['Welcome' => 'Welcome', 'Goodbye' => 'Goodbye']);
    });

    it('creates parent directories when writing', function () {
        $path = $this->tempDir.'/sub/dir/en.json';

        $this->repo->write($path, ['key' => 'value']);

        expect(file_exists($path))->toBeTrue();
    });

    it('round-trips unicode characters without escaping', function () {
        $path = $this->tempDir.'/en.json';
        $this->repo->write($path, ['Héllo' => 'Héllo', '日本語' => '日本語']);

        $content = file_get_contents($path);
        expect($content)->toContain('Héllo')
            ->and($content)->toContain('日本語');
    });

    it('preview returns serialised JSON without writing', function () {
        $path = $this->tempDir.'/en.json';
        $preview = $this->repo->preview(['Hello' => 'Hello']);

        expect(file_exists($path))->toBeFalse();
        expect($preview)->toContain('"Hello"');
        expect(json_decode($preview, true))->toBe(['Hello' => 'Hello']);
    });

    it('overwrites an existing file when writing', function () {
        $path = $this->tempDir.'/en.json';
        file_put_contents($path, json_encode(['old' => 'value']));

        $this->repo->write($path, ['new' => 'value']);

        $data = json_decode(file_get_contents($path), true);
        expect($data)->toBe(['new' => 'value']);
    });
});
