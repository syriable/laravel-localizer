<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Syriable\Localizer\Exceptions\LocalizerException;
use Syriable\Localizer\Support\FileHasher;

use function Syriable\Localizer\Tests\removeDir;

beforeEach(function () {
    $this->hasher = new FileHasher(new Filesystem);
    $this->tempDir = sys_get_temp_dir().'/hasher-test-'.bin2hex(random_bytes(4));
    mkdir($this->tempDir, 0o755, true);
});

afterEach(function () {
    removeDir($this->tempDir);
});

describe('FileHasher', function () {
    it('hashes a file deterministically', function () {
        $path = $this->tempDir.'/sample.txt';
        file_put_contents($path, 'hello world');

        $hash1 = $this->hasher->hashFile($path);
        $hash2 = $this->hasher->hashFile($path);

        expect($hash1)->toBe($hash2);
    });

    it('prefixes the hash with the algorithm name', function () {
        $path = $this->tempDir.'/sample.txt';
        file_put_contents($path, 'hello');

        expect($this->hasher->hashFile($path))->toStartWith('xxh128:');
    });

    it('produces different hashes for different contents', function () {
        $a = $this->tempDir.'/a.txt';
        $b = $this->tempDir.'/b.txt';
        file_put_contents($a, 'aaa');
        file_put_contents($b, 'bbb');

        expect($this->hasher->hashFile($a))->not->toBe($this->hasher->hashFile($b));
    });

    it('throws when file does not exist', function () {
        $this->hasher->hashFile($this->tempDir.'/missing.txt');
    })->throws(LocalizerException::class, 'non-existent file');

    it('hashes strings deterministically', function () {
        expect($this->hasher->hashString('abc'))->toBe($this->hasher->hashString('abc'))
            ->and($this->hasher->hashString('abc'))->not->toBe($this->hasher->hashString('xyz'));
    });

    it('produces the same hash for string and file with identical contents', function () {
        $path = $this->tempDir.'/identical.txt';
        $contents = 'consistent contents';
        file_put_contents($path, $contents);

        expect($this->hasher->hashFile($path))->toBe($this->hasher->hashString($contents));
    });
});
