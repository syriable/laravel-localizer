<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Syriable\Localizer\Generator\TranslationFileRepository;
use Syriable\Localizer\Generator\TranslationPhpRenderer;
use Syriable\Localizer\Support\AtomicWriter;

use function Syriable\Localizer\Tests\removeDir;
use function Syriable\Localizer\Tests\writeFile;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/repo-test-'.bin2hex(random_bytes(4));
    mkdir($this->tempDir, 0o755, true);

    $files = new Filesystem;
    $this->repository = new TranslationFileRepository(
        files: $files,
        writer: new AtomicWriter($files),
        renderer: new TranslationPhpRenderer,
    );
});

afterEach(function () {
    removeDir($this->tempDir);
});

describe('TranslationFileRepository', function () {
    it('returns false for a non-existing path', function () {
        expect($this->repository->exists($this->tempDir.'/missing.php'))->toBeFalse();
    });

    it('returns true for an existing file', function () {
        $path = writeFile($this->tempDir, 'lang/en/test.php', "<?php\nreturn [];");

        expect($this->repository->exists($path))->toBeTrue();
    });

    it('returns an empty array for a non-existing file', function () {
        expect($this->repository->read($this->tempDir.'/missing.php'))->toBe([]);
    });

    it('reads an existing PHP translation file', function () {
        $path = writeFile($this->tempDir, 'en/pagination.php', "<?php\nreturn ['next' => 'Next', 'prev' => 'Previous'];");

        expect($this->repository->read($path))->toBe(['next' => 'Next', 'prev' => 'Previous']);
    });

    it('returns an empty array when the file does not return an array', function () {
        $path = writeFile($this->tempDir, 'broken.php', "<?php\nreturn 'not an array';");

        expect($this->repository->read($path))->toBe([]);
    });

    it('writes a PHP file with the rendered array', function () {
        $path = $this->tempDir.'/output.php';
        $this->repository->write($path, ['key' => 'value']);

        expect(file_exists($path))->toBeTrue();
        $loaded = include $path;
        expect($loaded)->toBe(['key' => 'value']);
    });

    it('creates parent directories when writing', function () {
        $path = $this->tempDir.'/deep/nested/dir/output.php';
        $this->repository->write($path, ['a' => 'b']);

        expect(file_exists($path))->toBeTrue();
    });

    it('preview() returns rendered PHP without touching the filesystem', function () {
        $path = $this->tempDir.'/preview.php';
        $content = $this->repository->preview(['key' => 'value']);

        expect(file_exists($path))->toBeFalse()
            ->and($content)->toContain("'key' => 'value'");
    });

    it('round-trips nested data through write + read', function () {
        $path = $this->tempDir.'/roundtrip.php';
        $data = [
            'auth' => ['login' => 'Login', 'logout' => 'Logout'],
            'welcome' => 'Welcome',
        ];

        $this->repository->write($path, $data);
        $loaded = $this->repository->read($path);

        expect($loaded)->toBe($data);
    });
});
