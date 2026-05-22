<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Syriable\Localizer\Support\AtomicWriter;

use function Syriable\Localizer\Tests\removeDir;

beforeEach(function () {
    $this->writer = new AtomicWriter(new Filesystem);
    $this->tempDir = sys_get_temp_dir().'/atomic-test-'.bin2hex(random_bytes(4));
    mkdir($this->tempDir, 0o755, true);
});

afterEach(function () {
    removeDir($this->tempDir);
});

describe('AtomicWriter', function () {
    it('writes new files', function () {
        $path = $this->tempDir.'/new.txt';

        $this->writer->write($path, 'hello');

        expect(file_get_contents($path))->toBe('hello');
    });

    it('overwrites existing files atomically', function () {
        $path = $this->tempDir.'/existing.txt';
        file_put_contents($path, 'original');

        $this->writer->write($path, 'replaced');

        expect(file_get_contents($path))->toBe('replaced');
    });

    it('creates parent directories as needed', function () {
        $path = $this->tempDir.'/nested/sub/file.txt';

        $this->writer->write($path, 'contents');

        expect(file_exists($path))->toBeTrue()
            ->and(file_get_contents($path))->toBe('contents');
    });

    it('leaves no temp file behind on success', function () {
        $path = $this->tempDir.'/final.txt';

        $this->writer->write($path, 'data');

        $tempFiles = glob($this->tempDir.'/*.tmp');
        expect($tempFiles)->toBe([]);
    });
});
