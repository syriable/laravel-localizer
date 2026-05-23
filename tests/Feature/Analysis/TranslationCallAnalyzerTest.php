<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Syriable\Localizer\Analysis\PhpExpressionClassifier;
use Syriable\Localizer\Analysis\PlaceholderType;
use Syriable\Localizer\Analysis\TranslationCallAnalyzer;
use Syriable\Localizer\Analysis\TranslationSourceParser;
use Syriable\Localizer\Support\StringClassifier;

beforeEach(function () {
    $this->analyzer = new TranslationCallAnalyzer(
        files: new Filesystem,
        parser: new TranslationSourceParser,
        classifier: new PhpExpressionClassifier,
        stringClassifier: new StringClassifier,
    );
});

describe('TranslationCallAnalyzer', function () {
    it('analyses a single object-property placeholder', function () {
        $source = "<?php __('hello', ['user' => \$user->name]);";

        $analyses = $this->analyzer->analyze($source, '/tmp/test.php');

        expect($analyses)->toHaveCount(1);

        $a = $analyses[0];
        expect($a->key)->toBe('hello');
        expect($a->placeholders)->toHaveCount(1);

        $p = $a->placeholders[0];
        expect($p->placeholder)->toBe(':user');
        expect($p->source)->toBe('$user->name');
        expect($p->type)->toBe(PlaceholderType::ObjectProperty);
        expect($p->structure)->toBe([
            'object' => '$user',
            'property' => 'name',
        ]);
    });

    it('produces a humanised lang_example for ShortKey calls', function () {
        $source = "<?php __('greeting.welcome', ['user' => \$user->name]);";

        $analyses = $this->analyzer->analyze($source, '/tmp/test.php');

        expect($analyses[0]->langExample)->toBe([
            'greeting.welcome' => 'Welcome :user',
        ]);
    });

    it('produces a JsonKey-shaped lang_example for free-text strings', function () {
        $source = "<?php __('Welcome back', ['user' => \$user->name]);";

        $analyses = $this->analyzer->analyze($source, '/tmp/test.php');

        $example = $analyses[0]->langExample;

        expect($example)->toHaveKey('Welcome back');
        expect($example['Welcome back'])->toContain(':user');
    });

    it('matches the canonical structured output example', function () {
        $source = <<<'PHP'
<?php
__('welcome', [
    'name' => $user->profile->full_name,
    'count' => getOrdersCount($user->id),
]);
PHP;

        $analyses = $this->analyzer->analyze($source, '/tmp/test.php');
        $serialised = $analyses[0]->toArray();

        expect($serialised['key'])->toBe('welcome');
        expect($serialised['placeholders'])->toHaveCount(2);

        expect($serialised['placeholders'][0])->toMatchArray([
            'placeholder' => ':name',
            'source' => '$user->profile->full_name',
            'type' => 'nested_object_property',
            'structure' => [
                'object' => '$user',
                'path' => ['profile', 'full_name'],
            ],
        ]);

        expect($serialised['placeholders'][1])->toMatchArray([
            'placeholder' => ':count',
            'source' => 'getOrdersCount($user->id)',
            'type' => 'function_call',
            'structure' => [
                'function' => 'getOrdersCount',
                'arguments' => ['$user->id'],
            ],
        ]);
    });

    it('analyses many call sites in a single source file', function () {
        $source = <<<'PHP'
<?php
__('first');
trans('second', ['name' => $name]);
@lang('third', ['user' => $user->name])
trans_choice('fourth', $n, ['count' => $n]);
PHP;

        $analyses = $this->analyzer->analyze($source, '/tmp/test.php');

        expect($analyses)->toHaveCount(4);
        expect(array_map(static fn ($a) => $a->key, $analyses))
            ->toBe(['first', 'second', 'third', 'fourth']);
        expect(array_map(static fn ($a) => $a->functionName, $analyses))
            ->toBe(['__', 'trans', '@lang', 'trans_choice']);
    });

    it('returns an empty list when reading a missing file', function () {
        $analyses = $this->analyzer->analyzeFile('/no/such/file.php');

        expect($analyses)->toBe([]);
    });

    it('reads from disk via analyzeFile', function () {
        $path = sys_get_temp_dir().'/analyze-'.bin2hex(random_bytes(4)).'.php';
        file_put_contents($path, "<?php __('on.disk', ['x' => \$y]);");

        try {
            $analyses = $this->analyzer->analyzeFile($path);

            expect($analyses)->toHaveCount(1);
            expect($analyses[0]->key)->toBe('on.disk');
            expect($analyses[0]->placeholders[0]->source)->toBe('$y');
        } finally {
            unlink($path);
        }
    });

    it('serialises the full analysis to a stable array shape', function () {
        $source = "<?php __('user.greeting', ['user' => User::find(1)]);";

        $analyses = $this->analyzer->analyze($source, '/tmp/x.php');
        $array = $analyses[0]->toArray();

        expect(array_keys($array))->toBe(['key', 'function', 'location', 'placeholders', 'lang_example']);
        expect($array['placeholders'][0]['type'])->toBe('static_method_call');
        expect($array['placeholders'][0]['structure'])->toBe([
            'class' => 'User',
            'method' => 'find',
            'arguments' => ['1'],
        ]);
    });
});
