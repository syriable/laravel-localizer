<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

describe('AnalyzeCommand', function () {
    it('exits successfully with no calls found', function () {
        $this->artisan('localizer:analyze')->assertSuccessful();
    });

    it('lists discovered calls in a table by default', function () {
        $this->writeFixture('app/Greeter.php', "<?php __('hello', ['user' => \$user->name]);");

        $this->artisan('localizer:analyze')
            ->expectsOutputToContain('hello')
            ->assertSuccessful();
    });

    it('outputs JSON when --json is passed', function () {
        $this->writeFixture('app/Greeter.php', "<?php __('hello', ['user' => \$user->name]);");

        Artisan::call('localizer:analyze', ['--json' => true]);
        $output = Artisan::output();

        $decoded = json_decode($output, true);

        expect($decoded)->toBeArray()->and($decoded)->toHaveCount(1);
        expect($decoded[0]['key'])->toBe('hello');
        expect($decoded[0]['placeholders'][0]['placeholder'])->toBe(':user');
        expect($decoded[0]['placeholders'][0]['type'])->toBe('object_property');
    });

    it('filters output by --key', function () {
        $this->writeFixture('app/A.php', "<?php __('keep.me');");
        $this->writeFixture('app/B.php', "<?php __('drop.me');");

        Artisan::call('localizer:analyze', ['--json' => true, '--key' => 'keep.me']);
        $decoded = json_decode(Artisan::output(), true);

        expect($decoded)->toHaveCount(1);
        expect($decoded[0]['key'])->toBe('keep.me');
    });

    it('accepts custom paths as arguments', function () {
        $custom = $this->tempPath.'/custom';
        mkdir($custom, 0o755, true);
        file_put_contents($custom.'/x.php', "<?php __('custom.key', ['name' => \$name]);");

        $this->artisan('localizer:analyze', ['paths' => [$custom]])
            ->expectsOutputToContain('custom.key')
            ->assertSuccessful();
    });

    it('classifies a variety of placeholder expressions', function () {
        $this->writeFixture('app/Variety.php', <<<'PHP'
<?php
__('a', ['v' => $name]);
__('b', ['v' => $user->name]);
__('c', ['v' => $user->profile->avatar]);
__('d', ['v' => getUser()]);
__('e', ['v' => $user->orders()]);
__('f', ['v' => User::find(1)]);
__('g', ['v' => 42]);
__('h', ['v' => $x + $y]);
PHP);

        Artisan::call('localizer:analyze', ['--json' => true]);
        $decoded = json_decode(Artisan::output(), true);

        $types = array_map(static fn ($a) => $a['placeholders'][0]['type'], $decoded);

        expect($types)->toBe([
            'variable',
            'object_property',
            'nested_object_property',
            'function_call',
            'method_call',
            'static_method_call',
            'literal',
            'expression',
        ]);
    });
});
