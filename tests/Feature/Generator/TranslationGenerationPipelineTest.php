<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Syriable\Localizer\Data\ExtractedString;
use Syriable\Localizer\Data\GenerationRequest;
use Syriable\Localizer\Data\ScanResult;
use Syriable\Localizer\Data\StringKind;
use Syriable\Localizer\Generator\Strategies\EmptyStrategy;
use Syriable\Localizer\Generator\Strategies\HumanizedStrategy;
use Syriable\Localizer\Generator\Strategies\KeyStrategy;
use Syriable\Localizer\Generator\StrategyRegistry;
use Syriable\Localizer\Generator\TranslationArrayBuilder;
use Syriable\Localizer\Generator\TranslationFileGenerator;
use Syriable\Localizer\Generator\TranslationFileRepository;
use Syriable\Localizer\Generator\TranslationGenerationPipeline;
use Syriable\Localizer\Generator\TranslationMergeService;
use Syriable\Localizer\Generator\TranslationPhpRenderer;
use Syriable\Localizer\Support\AtomicWriter;

use function Syriable\Localizer\Tests\removeDir;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/pipeline-test-'.bin2hex(random_bytes(4));
    mkdir($this->tempDir, 0o755, true);

    $files = new Filesystem;
    $repository = new TranslationFileRepository(
        files: $files,
        writer: new AtomicWriter($files),
        renderer: new TranslationPhpRenderer,
    );
    $fileGenerator = new TranslationFileGenerator(
        builder: new TranslationArrayBuilder,
        merger: new TranslationMergeService,
        repository: $repository,
    );
    $registry = new StrategyRegistry;
    $registry->register(new HumanizedStrategy);
    $registry->register(new KeyStrategy);
    $registry->register(new EmptyStrategy);

    $this->pipeline = new TranslationGenerationPipeline(
        fileGenerator: $fileGenerator,
        strategies: $registry,
    );
});

afterEach(function () {
    removeDir($this->tempDir);
});

function makeScanResultFromStrings(ExtractedString ...$strings): ScanResult
{
    return new ScanResult(
        strings: array_values($strings),
        filesScanned: 1,
        filesFromCache: 0,
        durationMs: 1.0,
    );
}

describe('TranslationGenerationPipeline', function () {
    it('generates a file for a single short-key string', function () {
        $string = makeExtractedString('pagination.next', StringKind::ShortKey);
        $result = makeScanResultFromStrings($string);

        $request = new GenerationRequest(
            result: $result,
            locale: 'en',
            strategy: 'key',
            basePath: $this->tempDir,
        );

        $outcome = $this->pipeline->run($request);

        expect($outcome->filesWritten())->toBe(1)
            ->and($outcome->keysAdded)->toBeGreaterThan(0);
    });

    it('generates separate files for strings with different target files', function () {
        $strings = [
            makeExtractedString('pagination.next', StringKind::ShortKey),
            makeExtractedString('auth.login', StringKind::ShortKey),
        ];
        $result = makeScanResultFromStrings(...$strings);

        $request = new GenerationRequest(
            result: $result,
            locale: 'en',
            strategy: 'key',
            basePath: $this->tempDir,
        );

        $outcome = $this->pipeline->run($request);

        expect($outcome->filesWritten())->toBe(2);
    });

    it('generates a vendor namespace file for packaged strings', function () {
        $string = makeExtractedString('acme::buttons.submit', StringKind::ShortKey);
        $result = makeScanResultFromStrings($string);

        $request = new GenerationRequest(
            result: $result,
            locale: 'en',
            strategy: 'key',
            basePath: $this->tempDir,
        );

        $outcome = $this->pipeline->run($request);

        expect($outcome->filesWritten())->toBe(1);
        $writtenPath = $outcome->written[0];
        expect($writtenPath)->toContain('vendor/acme');
    });

    it('skips json-key strings', function () {
        $jsonKey = makeExtractedString('Welcome back');
        $result = makeScanResultFromStrings($jsonKey);

        $request = new GenerationRequest(
            result: $result,
            locale: 'en',
            strategy: 'key',
            basePath: $this->tempDir,
        );

        $outcome = $this->pipeline->run($request);

        expect($outcome->filesWritten())->toBe(0)
            ->and($outcome->filesSkipped())->toBe(0);
    });

    it('filters by namespace when namespace is set', function () {
        $appString = makeExtractedString('buttons.submit', StringKind::ShortKey);
        $vendorString = makeExtractedString('acme::buttons.submit', StringKind::ShortKey);
        $result = makeScanResultFromStrings($appString, $vendorString);

        $request = new GenerationRequest(
            result: $result,
            locale: 'en',
            strategy: 'key',
            basePath: $this->tempDir,
            namespace: 'acme',
        );

        $outcome = $this->pipeline->run($request);

        expect($outcome->filesWritten())->toBe(1);
        expect($outcome->written[0])->toContain('vendor/acme');
    });

    it('filters to app strings when namespace is empty string', function () {
        $appString = makeExtractedString('buttons.submit', StringKind::ShortKey);
        $vendorString = makeExtractedString('acme::buttons.submit', StringKind::ShortKey);
        $result = makeScanResultFromStrings($appString, $vendorString);

        $request = new GenerationRequest(
            result: $result,
            locale: 'en',
            strategy: 'key',
            basePath: $this->tempDir,
            namespace: '',
        );

        $outcome = $this->pipeline->run($request);

        expect($outcome->filesWritten())->toBe(1);
        expect($outcome->written[0])->not->toContain('vendor');
    });

    it('includes all strings when namespace is null', function () {
        $appString = makeExtractedString('buttons.submit', StringKind::ShortKey);
        $vendorString = makeExtractedString('acme::buttons.submit', StringKind::ShortKey);
        $result = makeScanResultFromStrings($appString, $vendorString);

        $request = new GenerationRequest(
            result: $result,
            locale: 'en',
            strategy: 'key',
            basePath: $this->tempDir,
            namespace: null,
        );

        $outcome = $this->pipeline->run($request);

        expect($outcome->filesWritten())->toBe(2);
    });

    it('returns dry-run preview without writing files', function () {
        $string = makeExtractedString('pagination.next', StringKind::ShortKey);
        $result = makeScanResultFromStrings($string);

        $request = new GenerationRequest(
            result: $result,
            locale: 'en',
            strategy: 'key',
            basePath: $this->tempDir,
            dryRun: true,
        );

        $outcome = $this->pipeline->run($request);

        expect($outcome->dryRun)->toBeTrue()
            ->and($outcome->filesWritten())->toBe(1)
            ->and($outcome->preview)->not->toBeEmpty()
            ->and(array_values($outcome->preview)[0])->toContain('next');

        // No files actually written
        expect(is_dir($this->tempDir.'/lang'))->toBeFalse();
    });

    it('deduplicates strings before generating', function () {
        // Same string twice → should produce exactly one key in the file
        $a = makeExtractedString('pagination.next', StringKind::ShortKey);
        $b = makeExtractedString('pagination.next', StringKind::ShortKey);
        $result = makeScanResultFromStrings($a, $b);

        $request = new GenerationRequest(
            result: $result,
            locale: 'en',
            strategy: 'key',
            basePath: $this->tempDir,
        );

        $outcome = $this->pipeline->run($request);
        expect($outcome->filesWritten())->toBe(1)
            ->and($outcome->keysAdded)->toBe(1);
    });

    it('does not overwrite existing values by default', function () {
        // Pre-create the file with a custom value.
        $langDir = $this->tempDir.'/lang/en';
        mkdir($langDir, 0o755, true);
        file_put_contents($langDir.'/pagination.php', "<?php\nreturn ['next' => 'Custom Next'];");

        $string = makeExtractedString('pagination.next', StringKind::ShortKey);
        $result = makeScanResultFromStrings($string);

        $request = new GenerationRequest(
            result: $result,
            locale: 'en',
            strategy: 'key',
            basePath: $this->tempDir,
        );

        $outcome = $this->pipeline->run($request);

        expect($outcome->filesSkipped())->toBe(1);
        $loaded = include $langDir.'/pagination.php';
        expect($loaded['next'])->toBe('Custom Next');
    });

    it('overwrites existing values when force is true', function () {
        $langDir = $this->tempDir.'/lang/en';
        mkdir($langDir, 0o755, true);
        file_put_contents($langDir.'/pagination.php', "<?php\nreturn ['next' => 'Custom Next'];");

        $string = makeExtractedString('pagination.next', StringKind::ShortKey);
        $result = makeScanResultFromStrings($string);

        $request = new GenerationRequest(
            result: $result,
            locale: 'en',
            strategy: 'key',
            basePath: $this->tempDir,
            force: true,
        );

        $this->pipeline->run($request);

        $loaded = include $langDir.'/pagination.php';
        expect($loaded['next'])->toBe('next');
    });

    it('generates files with the correct locale path segment', function () {
        $string = makeExtractedString('pagination.next', StringKind::ShortKey);
        $result = makeScanResultFromStrings($string);

        $request = new GenerationRequest(
            result: $result,
            locale: 'fr',
            strategy: 'key',
            basePath: $this->tempDir,
        );

        $outcome = $this->pipeline->run($request);

        expect($outcome->written[0])->toContain('/fr/');
    });

    it('generates for deeply nested directory keys', function () {
        $string = makeExtractedString('profile/btn/form.submit.label', StringKind::ShortKey);
        $result = makeScanResultFromStrings($string);

        $request = new GenerationRequest(
            result: $result,
            locale: 'en',
            strategy: 'humanized',
            basePath: $this->tempDir,
        );

        $outcome = $this->pipeline->run($request);

        expect($outcome->filesWritten())->toBe(1);
        $written = str_replace('\\', '/', $outcome->written[0]);
        expect($written)->toContain('profile/btn/form.php');
    });
});
