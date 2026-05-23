<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Syriable\Localizer\Data\StringKind;
use Syriable\Localizer\Generator\Strategies\HumanizedStrategy;
use Syriable\Localizer\Generator\Strategies\KeyStrategy;
use Syriable\Localizer\Generator\TranslationArrayBuilder;
use Syriable\Localizer\Generator\TranslationFileGenerator;
use Syriable\Localizer\Generator\TranslationFileRepository;
use Syriable\Localizer\Generator\TranslationMergeService;
use Syriable\Localizer\Generator\TranslationPhpRenderer;
use Syriable\Localizer\Support\AtomicWriter;

use function Syriable\Localizer\Tests\removeDir;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/gen-test-'.bin2hex(random_bytes(4));
    mkdir($this->tempDir, 0o755, true);

    $files = new Filesystem;
    $repository = new TranslationFileRepository(
        files: $files,
        writer: new AtomicWriter($files),
        renderer: new TranslationPhpRenderer,
    );

    $this->generator = new TranslationFileGenerator(
        builder: new TranslationArrayBuilder,
        merger: new TranslationMergeService,
        repository: $repository,
    );
    $this->strategy = new KeyStrategy;
    $this->humanized = new HumanizedStrategy;
});

afterEach(function () {
    removeDir($this->tempDir);
});

describe('TranslationFileGenerator', function () {
    it('creates a new file when it does not exist', function () {
        $path = $this->tempDir.'/lang/en/pagination.php';
        $strings = [makeExtractedString('pagination.next', StringKind::ShortKey)];

        $outcome = $this->generator->generate($path, $strings, $this->strategy, force: false, dryRun: false);

        expect($outcome->written)->toBeTrue()
            ->and($outcome->keysAdded)->toBeGreaterThan(0)
            ->and(file_exists($path))->toBeTrue();
    });

    it('adds missing keys to an existing file', function () {
        $path = $this->tempDir.'/lang/en/buttons.php';
        mkdir(dirname($path), 0o755, true);
        file_put_contents($path, "<?php\nreturn ['submit' => 'Submit'];");

        $strings = [
            makeExtractedString('buttons.submit', StringKind::ShortKey),
            makeExtractedString('buttons.cancel', StringKind::ShortKey),
        ];

        $outcome = $this->generator->generate($path, $strings, $this->strategy, force: false, dryRun: false);

        $loaded = include $path;

        expect($outcome->written)->toBeTrue()
            ->and($loaded['submit'])->toBe('Submit')
            ->and($loaded['cancel'])->toBe('buttons.cancel');
    });

    it('does not overwrite existing values without force', function () {
        $path = $this->tempDir.'/lang/en/auth.php';
        mkdir(dirname($path), 0o755, true);
        file_put_contents($path, "<?php\nreturn ['login' => 'Custom login'];");

        $strings = [makeExtractedString('auth.login', StringKind::ShortKey)];

        $this->generator->generate($path, $strings, $this->strategy, force: false, dryRun: false);

        $loaded = include $path;
        expect($loaded['login'])->toBe('Custom login');
    });

    it('overwrites existing values when force is true', function () {
        $path = $this->tempDir.'/lang/en/auth.php';
        mkdir(dirname($path), 0o755, true);
        file_put_contents($path, "<?php\nreturn ['login' => 'Custom login'];");

        $strings = [makeExtractedString('auth.login', StringKind::ShortKey)];

        $this->generator->generate($path, $strings, $this->strategy, force: true, dryRun: false);

        $loaded = include $path;
        expect($loaded['login'])->toBe('auth.login');
    });

    it('skips the file when no new keys are present', function () {
        $path = $this->tempDir.'/lang/en/pagination.php';
        mkdir(dirname($path), 0o755, true);
        file_put_contents($path, "<?php\nreturn ['next' => 'Next'];");

        $strings = [makeExtractedString('pagination.next', StringKind::ShortKey)];

        $outcome = $this->generator->generate($path, $strings, $this->strategy, force: false, dryRun: false);

        expect($outcome->written)->toBeFalse()
            ->and($outcome->keysAdded)->toBe(0);
    });

    it('returns preview content in dry-run mode without writing', function () {
        $path = $this->tempDir.'/lang/en/test.php';
        $strings = [makeExtractedString('test.key', StringKind::ShortKey)];

        $outcome = $this->generator->generate($path, $strings, $this->strategy, force: false, dryRun: true);

        expect($outcome->written)->toBeTrue()
            ->and($outcome->preview)->toContain("'key'")
            ->and(file_exists($path))->toBeFalse();
    });

    it('creates nested directory structure for deep paths', function () {
        $path = $this->tempDir.'/lang/en/profile/btn/form.php';
        $strings = [makeExtractedString('profile/btn/form.submit.label', StringKind::ShortKey)];

        $this->generator->generate($path, $strings, $this->humanized, force: false, dryRun: false);

        expect(file_exists($path))->toBeTrue();
        $loaded = include $path;
        expect($loaded['submit']['label'])->toBe('Label');
    });

    it('generates nested array from dot-notation key', function () {
        $path = $this->tempDir.'/lang/en/buttons.php';
        $strings = [makeExtractedString('buttons.submit.label', StringKind::ShortKey)];

        $this->generator->generate($path, $strings, $this->strategy, force: false, dryRun: false);

        $loaded = include $path;
        expect($loaded['submit']['label'])->toBe('buttons.submit.label');
    });

    it('applies the humanized strategy correctly', function () {
        $path = $this->tempDir.'/lang/en/forms.php';
        $strings = [makeExtractedString('forms.submit_btn', StringKind::ShortKey)];

        $this->generator->generate($path, $strings, $this->humanized, force: false, dryRun: false);

        $loaded = include $path;
        expect($loaded['submit_btn'])->toBe('Submit btn');
    });
});
