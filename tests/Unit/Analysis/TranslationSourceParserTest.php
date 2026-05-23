<?php

declare(strict_types=1);

use Syriable\Localizer\Analysis\TranslationSourceParser;

beforeEach(function () {
    $this->parser = new TranslationSourceParser;
});

function parseSource(TranslationSourceParser $parser, string $source): array
{
    return iterator_to_array($parser->parse($source, '/tmp/test.php'), preserve_keys: false);
}

describe('TranslationSourceParser', function () {
    it('parses a simple __() call with no replacements', function () {
        $source = "<?php\n__('hello');\n";

        $calls = parseSource($this->parser, $source);

        expect($calls)->toHaveCount(1);
        expect($calls[0]['function'])->toBe('__');
        expect($calls[0]['key'])->toBe('hello');
        expect($calls[0]['replacements'])->toBe([]);
        expect($calls[0]['location']->line)->toBe(2);
    });

    it('parses a __() call with one replacement', function () {
        $source = "<?php\n__('hello', ['user' => \$user->name]);\n";

        $calls = parseSource($this->parser, $source);

        expect($calls)->toHaveCount(1);
        expect($calls[0]['key'])->toBe('hello');
        expect($calls[0]['replacements'])->toBe([
            'user' => '$user->name',
        ]);
    });

    it('parses multiple replacements with different shapes', function () {
        $source = <<<'PHP'
<?php
__('welcome', [
    'name' => $user->profile->full_name,
    'count' => getOrdersCount($user->id),
]);
PHP;

        $calls = parseSource($this->parser, $source);

        expect($calls)->toHaveCount(1);
        expect($calls[0]['replacements'])->toBe([
            'name' => '$user->profile->full_name',
            'count' => 'getOrdersCount($user->id)',
        ]);
    });

    it('handles commas inside string-literal arguments', function () {
        $source = "<?php\n__('list', ['items' => implode(', ', \$items)]);\n";

        $calls = parseSource($this->parser, $source);

        expect($calls[0]['replacements'])->toBe([
            'items' => "implode(', ', \$items)",
        ]);
    });

    it('handles nested arrays inside replacement values', function () {
        $source = "<?php\n__('link', ['url' => route('show', ['id' => \$id])]);\n";

        $calls = parseSource($this->parser, $source);

        expect($calls[0]['replacements'])->toBe([
            'url' => "route('show', ['id' => \$id])",
        ]);
    });

    it('parses trans() with replacements as the second argument', function () {
        $source = "<?php\ntrans('auth.failed', ['name' => \$name]);\n";

        $calls = parseSource($this->parser, $source);

        expect($calls)->toHaveCount(1);
        expect($calls[0]['function'])->toBe('trans');
        expect($calls[0]['key'])->toBe('auth.failed');
        expect($calls[0]['replacements'])->toBe(['name' => '$name']);
    });

    it('parses trans_choice() with replacements as the third argument', function () {
        $source = "<?php\ntrans_choice('items.count', \$count, ['name' => \$user->name]);\n";

        $calls = parseSource($this->parser, $source);

        expect($calls)->toHaveCount(1);
        expect($calls[0]['function'])->toBe('trans_choice');
        expect($calls[0]['replacements'])->toBe(['name' => '$user->name']);
    });

    it('parses @lang() Blade directive', function () {
        $source = "@lang('greeting', ['user' => \$user->name])";

        $calls = parseSource($this->parser, $source);

        expect($calls)->toHaveCount(1);
        expect($calls[0]['function'])->toBe('@lang');
        expect($calls[0]['key'])->toBe('greeting');
        expect($calls[0]['replacements'])->toBe(['user' => '$user->name']);
    });

    it('parses Lang::get() static method call', function () {
        $source = "<?php\nLang::get('msg.welcome', ['name' => \$name]);\n";

        $calls = parseSource($this->parser, $source);

        expect($calls)->toHaveCount(1);
        expect($calls[0]['function'])->toBe('Lang::get');
        expect($calls[0]['replacements'])->toBe(['name' => '$name']);
    });

    it('parses Lang::choice() with replacements as the third argument', function () {
        $source = "<?php\nLang::choice('items', \$count, ['name' => \$name]);\n";

        $calls = parseSource($this->parser, $source);

        expect($calls[0]['function'])->toBe('Lang::choice');
        expect($calls[0]['replacements'])->toBe(['name' => '$name']);
    });

    it('skips calls with dynamic keys', function () {
        $source = "<?php\n__(\$dynamicKey, ['name' => \$name]);\n";

        $calls = parseSource($this->parser, $source);

        expect($calls)->toBe([]);
    });

    it('finds multiple calls in one file', function () {
        $source = <<<'PHP'
<?php
echo __('hello');
echo __('goodbye', ['name' => $user->name]);
echo trans('label.submit');
PHP;

        $calls = parseSource($this->parser, $source);

        expect($calls)->toHaveCount(3);
        expect(array_column($calls, 'key'))->toBe(['hello', 'goodbye', 'label.submit']);
    });

    it('captures correct line numbers for each call', function () {
        $source = "<?php\n\n__('first');\n\n\n__('second');\n";

        $calls = parseSource($this->parser, $source);

        expect($calls[0]['location']->line)->toBe(3);
        expect($calls[1]['location']->line)->toBe(6);
    });

    it('handles double-quoted keys', function () {
        $source = '<?php __("welcome", ["name" => $name]);';

        $calls = parseSource($this->parser, $source);

        expect($calls[0]['key'])->toBe('welcome');
        expect($calls[0]['replacements'])->toBe(['name' => '$name']);
    });

    it('does not confuse a method call with __ in the name', function () {
        $source = '<?php $foo->__(123);';

        $calls = parseSource($this->parser, $source);

        expect($calls)->toBe([]);
    });

    it('handles whitespace and newlines between function name and parens', function () {
        $source = "<?php __  (\n  'key',\n  ['name' => \$name]\n);";

        $calls = parseSource($this->parser, $source);

        expect($calls)->toHaveCount(1);
        expect($calls[0]['key'])->toBe('key');
        expect($calls[0]['replacements'])->toBe(['name' => '$name']);
    });

    it('preserves verbatim expressions including operators', function () {
        $source = "<?php\n__('price', ['total' => \$order->subtotal + \$tax]);\n";

        $calls = parseSource($this->parser, $source);

        expect($calls[0]['replacements'])->toBe([
            'total' => '$order->subtotal + $tax',
        ]);
    });

    it('handles escaped quotes in string keys', function () {
        $source = "<?php __('it\\'s here', ['n' => 1]);";

        $calls = parseSource($this->parser, $source);

        expect($calls[0]['key'])->toBe("it's here");
    });
});

describe('TranslationSourceParser — comment stripping', function () {
    it('ignores translation calls inside PHP // comments', function () {
        $source = "<?php\n// __('ignored.key')\n__('real.key');\n";

        $calls = parseSource($this->parser, $source);

        expect($calls)->toHaveCount(1);
        expect($calls[0]['key'])->toBe('real.key');
    });

    it('ignores translation calls inside PHP # comments', function () {
        $source = "<?php\n# __('ignored.key')\n__('real.key');\n";

        $calls = parseSource($this->parser, $source);

        expect($calls)->toHaveCount(1);
        expect($calls[0]['key'])->toBe('real.key');
    });

    it('ignores translation calls inside PHP block comments', function () {
        $source = <<<'PHP'
<?php
/*
 * __('block.ignored')
 */
__('real.key');
PHP;
        $calls = parseSource($this->parser, $source);

        expect($calls)->toHaveCount(1);
        expect($calls[0]['key'])->toBe('real.key');
    });

    it('ignores translation calls inside Blade {{-- --}} comments', function () {
        $source = "{{-- __('blade.ignored') --}}\n{{ __('blade.real') }}";

        $calls = parseSource($this->parser, $source);

        expect($calls)->toHaveCount(1);
        expect($calls[0]['key'])->toBe('blade.real');
    });

    it('ignores translation calls inside HTML <!-- --> comments', function () {
        $source = "<!-- __('html.ignored') -->\n{{ __('html.real') }}";

        $calls = parseSource($this->parser, $source);

        expect($calls)->toHaveCount(1);
        expect($calls[0]['key'])->toBe('html.real');
    });

    it('still correctly reports line numbers after comment stripping', function () {
        $source = "<?php\n// ignored\n__('real.key');\n";

        $calls = parseSource($this->parser, $source);

        expect($calls[0]['location']->line)->toBe(3);
    });
});

describe('TranslationSourceParser — robust placeholder extraction', function () {
    it('extracts placeholders from a single-line array', function () {
        $source = "<?php __('messages.welcome', ['name' => \$user->name]);";

        $calls = parseSource($this->parser, $source);

        expect($calls[0]['replacements'])->toBe(['name' => '$user->name']);
    });

    it('extracts placeholders from a multiline array (trailing comma)', function () {
        $source = <<<'PHP'
<?php
__('messages.welcome', [
    'name' => $user->name,
]);
PHP;
        $calls = parseSource($this->parser, $source);

        expect($calls[0]['replacements'])->toBe(['name' => '$user->name']);
    });

    it('extracts multiple placeholders from a single-line array', function () {
        $source = "<?php trans('mail.sent', ['email' => \$email, 'count' => \$count]);";

        $calls = parseSource($this->parser, $source);

        expect($calls[0]['replacements'])->toBe([
            'email' => '$email',
            'count' => '$count',
        ]);
    });

    it('extracts multiple placeholders from a multiline array with trailing commas', function () {
        $source = <<<'PHP'
<?php
trans('mail.sent', [
    'email' => $email,
    'count' => $count,
]);
PHP;
        $calls = parseSource($this->parser, $source);

        expect($calls[0]['replacements'])->toBe([
            'email' => '$email',
            'count' => '$count',
        ]);
    });

    it('extracts placeholder names from a call with a literal array value', function () {
        // The VALUE ('email') is irrelevant — only the KEY ('label') matters
        // for placeholder name extraction. The generated translation must use
        // ':label', not the literal value.
        $source = "<?php __('actions.send', ['label' => 'email']);";

        $calls = parseSource($this->parser, $source);

        expect(array_keys($calls[0]['replacements']))->toBe(['label']);
    });
});
