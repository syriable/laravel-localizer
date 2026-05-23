<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Syriable\Localizer\Analysis\PlaceholderAnalysis;
use Syriable\Localizer\Analysis\PlaceholderType;
use Syriable\Localizer\Analysis\TranslationCallAnalysis;
use Syriable\Localizer\Data\ExtractedString;
use Syriable\Localizer\Data\GenerationRequest;
use Syriable\Localizer\Data\ScanResult;
use Syriable\Localizer\Data\SourceLocation;
use Syriable\Localizer\Data\StringKind;
use Syriable\Localizer\Generator\Strategies\EmptyStrategy;
use Syriable\Localizer\Generator\Strategies\HumanizedStrategy;
use Syriable\Localizer\Generator\Strategies\KeyStrategy;
use Syriable\Localizer\Generator\StrategyRegistry;
use Syriable\Localizer\Generator\TranslationArrayBuilder;
use Syriable\Localizer\Generator\TranslationFileGenerator;
use Syriable\Localizer\Generator\TranslationFileRepository;
use Syriable\Localizer\Generator\TranslationGenerationPipeline;
use Syriable\Localizer\Generator\TranslationJsonFileGenerator;
use Syriable\Localizer\Generator\TranslationJsonRepository;
use Syriable\Localizer\Generator\TranslationMergeService;
use Syriable\Localizer\Generator\TranslationPhpRenderer;
use Syriable\Localizer\Support\AtomicWriter;

use function Syriable\Localizer\Tests\removeDir;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/pipeline-test-'.bin2hex(random_bytes(4));
    mkdir($this->tempDir, 0o755, true);

    $files = new Filesystem;
    $merger = new TranslationMergeService;

    $repository = new TranslationFileRepository(
        files: $files,
        writer: new AtomicWriter($files),
        renderer: new TranslationPhpRenderer,
    );
    $fileGenerator = new TranslationFileGenerator(
        builder: new TranslationArrayBuilder,
        merger: $merger,
        repository: $repository,
    );

    $jsonRepository = new TranslationJsonRepository(
        files: $files,
        writer: new AtomicWriter($files),
    );
    $jsonFileGenerator = new TranslationJsonFileGenerator(
        merger: $merger,
        repository: $jsonRepository,
    );

    $registry = new StrategyRegistry;
    $registry->register(new HumanizedStrategy);
    $registry->register(new KeyStrategy);
    $registry->register(new EmptyStrategy);

    $this->pipeline = new TranslationGenerationPipeline(
        fileGenerator: $fileGenerator,
        jsonFileGenerator: $jsonFileGenerator,
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

    it('generates a JSON file for json-key strings', function () {
        $jsonKey = makeExtractedString('Welcome back');
        $result = makeScanResultFromStrings($jsonKey);

        $request = new GenerationRequest(
            result: $result,
            locale: 'en',
            strategy: 'key',
            basePath: $this->tempDir,
        );

        $outcome = $this->pipeline->run($request);

        expect($outcome->filesWritten())->toBe(1);

        $jsonPath = $this->tempDir.'/lang/en.json';
        expect(file_exists($jsonPath))->toBeTrue();

        $data = json_decode(file_get_contents($jsonPath), true);
        expect($data)->toHaveKey('Welcome back')
            ->and($data['Welcome back'])->toBe('Welcome back');
    });

    it('writes json-key string value through the active strategy', function () {
        $jsonKey = makeExtractedString('welcome_back');
        $result = makeScanResultFromStrings($jsonKey);

        $request = new GenerationRequest(
            result: $result,
            locale: 'en',
            strategy: 'humanized',
            basePath: $this->tempDir,
        );

        $outcome = $this->pipeline->run($request);

        expect($outcome->filesWritten())->toBe(1);

        $data = json_decode(file_get_contents($this->tempDir.'/lang/en.json'), true);
        expect($data['welcome_back'])->toBe('Welcome back');
    });

    it('generates both PHP and JSON files in the same run', function () {
        $shortKey = makeExtractedString('pagination.next', StringKind::ShortKey);
        $jsonKey = makeExtractedString('Welcome back');
        $result = makeScanResultFromStrings($shortKey, $jsonKey);

        $request = new GenerationRequest(
            result: $result,
            locale: 'en',
            strategy: 'key',
            basePath: $this->tempDir,
        );

        $outcome = $this->pipeline->run($request);

        expect($outcome->filesWritten())->toBe(2);
        expect(file_exists($this->tempDir.'/lang/en/pagination.php'))->toBeTrue();
        expect(file_exists($this->tempDir.'/lang/en.json'))->toBeTrue();
    });

    it('does not write a JSON file for an empty json-key set', function () {
        // Only a ShortKey — no JsonKey strings at all
        $string = makeExtractedString('pagination.next', StringKind::ShortKey);
        $result = makeScanResultFromStrings($string);

        $request = new GenerationRequest(
            result: $result,
            locale: 'en',
            strategy: 'key',
            basePath: $this->tempDir,
        );

        $this->pipeline->run($request);

        expect(file_exists($this->tempDir.'/lang/en.json'))->toBeFalse();
    });

    it('does not overwrite existing JSON values by default', function () {
        $langDir = $this->tempDir.'/lang';
        mkdir($langDir, 0o755, true);
        file_put_contents($langDir.'/en.json', json_encode(['Welcome back' => 'Bienvenue']));

        $jsonKey = makeExtractedString('Welcome back');
        $result = makeScanResultFromStrings($jsonKey);

        $request = new GenerationRequest(
            result: $result,
            locale: 'en',
            strategy: 'key',
            basePath: $this->tempDir,
        );

        $outcome = $this->pipeline->run($request);

        expect($outcome->filesSkipped())->toBe(1);

        $data = json_decode(file_get_contents($langDir.'/en.json'), true);
        expect($data['Welcome back'])->toBe('Bienvenue');
    });

    it('overwrites existing JSON values when force is true', function () {
        $langDir = $this->tempDir.'/lang';
        mkdir($langDir, 0o755, true);
        file_put_contents($langDir.'/en.json', json_encode(['Welcome back' => 'Bienvenue']));

        $jsonKey = makeExtractedString('Welcome back');
        $result = makeScanResultFromStrings($jsonKey);

        $request = new GenerationRequest(
            result: $result,
            locale: 'en',
            strategy: 'key',
            basePath: $this->tempDir,
            force: true,
        );

        $this->pipeline->run($request);

        $data = json_decode(file_get_contents($langDir.'/en.json'), true);
        expect($data['Welcome back'])->toBe('Welcome back');
    });

    it('returns dry-run preview for JSON files without writing them', function () {
        $jsonKey = makeExtractedString('Welcome back');
        $result = makeScanResultFromStrings($jsonKey);

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
            ->and(array_values($outcome->preview)[0])->toContain('Welcome back');

        expect(file_exists($this->tempDir.'/lang/en.json'))->toBeFalse();
    });

    it('excludes json-key strings when namespace is a specific package', function () {
        $jsonKey = makeExtractedString('Welcome back');
        $result = makeScanResultFromStrings($jsonKey);

        $request = new GenerationRequest(
            result: $result,
            locale: 'en',
            strategy: 'key',
            basePath: $this->tempDir,
            namespace: 'acme',
        );

        $outcome = $this->pipeline->run($request);

        expect($outcome->filesWritten())->toBe(0);
        expect(file_exists($this->tempDir.'/lang/en.json'))->toBeFalse();
    });

    it('includes json-key strings when namespace is empty string', function () {
        $jsonKey = makeExtractedString('Welcome back');
        $result = makeScanResultFromStrings($jsonKey);

        $request = new GenerationRequest(
            result: $result,
            locale: 'en',
            strategy: 'key',
            basePath: $this->tempDir,
            namespace: '',
        );

        $outcome = $this->pipeline->run($request);

        expect($outcome->filesWritten())->toBe(1);
        expect(file_exists($this->tempDir.'/lang/en.json'))->toBeTrue();
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
        expect($loaded['next'])->toBe('pagination.next');
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

    it('analysis placeholder tokens appear in generated values for directory-prefixed keys', function () {
        // Regression: __('profile/form/buttons.submit.label', ['name' => $user->name])
        // previously generated 'Label' instead of 'Label :name' because
        // callAnalyses is keyed by the full value ('profile/form/buttons.submit.label')
        // while generate() was called with only the in-file key ('submit.label').
        $langDir = $this->tempDir.'/lang/en/profile/form';

        $analysis = new TranslationCallAnalysis(
            key: 'profile/form/buttons.submit.label',
            location: new SourceLocation('/tmp/test.php', 1),
            functionName: '__',
            placeholders: [
                new PlaceholderAnalysis(
                    placeholder: ':name',
                    source: '$user->name',
                    type: PlaceholderType::ObjectProperty,
                    structure: ['object' => '$user', 'property' => 'name'],
                ),
            ],
            langExample: [],
        );

        $string = makeExtractedString('profile/form/buttons.submit.label', StringKind::ShortKey);
        $result = makeScanResultFromStrings($string);

        $request = new GenerationRequest(
            result: $result,
            locale: 'en',
            strategy: 'humanized',
            basePath: $this->tempDir,
            callAnalyses: ['profile/form/buttons.submit.label' => $analysis],
        );

        $this->pipeline->run($request);

        $loaded = include $langDir.'/buttons.php';
        expect($loaded['submit']['label'])->toBe('Label :name');
    });
});
