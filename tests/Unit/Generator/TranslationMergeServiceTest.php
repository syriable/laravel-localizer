<?php

declare(strict_types=1);

use Syriable\Localizer\Generator\TranslationMergeService;

describe('TranslationMergeService', function () {
    beforeEach(function () {
        $this->service = new TranslationMergeService;
    });

    describe('merge()', function () {
        it('merges new keys into an empty existing array', function () {
            $result = $this->service->merge([], ['a' => 'A', 'b' => 'B']);

            expect($result)->toBe(['a' => 'A', 'b' => 'B']);
        });

        it('preserves existing keys when force is false', function () {
            $existing = ['a' => 'existing'];
            $new = ['a' => 'new', 'b' => 'B'];

            $result = $this->service->merge($existing, $new, force: false);

            expect($result['a'])->toBe('existing')
                ->and($result['b'])->toBe('B');
        });

        it('overwrites existing keys when force is true', function () {
            $existing = ['a' => 'existing'];
            $new = ['a' => 'new'];

            $result = $this->service->merge($existing, $new, force: true);

            expect($result['a'])->toBe('new');
        });

        it('merges nested arrays recursively', function () {
            $existing = ['auth' => ['login' => 'Login', 'register' => 'existing']];
            $new = ['auth' => ['register' => 'new', 'logout' => 'Logout']];

            $result = $this->service->merge($existing, $new, force: false);

            expect($result['auth']['login'])->toBe('Login')
                ->and($result['auth']['register'])->toBe('existing')
                ->and($result['auth']['logout'])->toBe('Logout');
        });

        it('overwrites nested values when force is true', function () {
            $existing = ['auth' => ['login' => 'existing']];
            $new = ['auth' => ['login' => 'new']];

            $result = $this->service->merge($existing, $new, force: true);

            expect($result['auth']['login'])->toBe('new');
        });

        it('adds new top-level nested arrays when key is absent', function () {
            $existing = [];
            $new = ['profile' => ['name' => 'Name', 'email' => 'Email']];

            $result = $this->service->merge($existing, $new);

            expect($result)->toBe(['profile' => ['name' => 'Name', 'email' => 'Email']]);
        });

        it('preserves keys not present in new', function () {
            $existing = ['a' => 'A', 'b' => 'B'];
            $new = ['c' => 'C'];

            $result = $this->service->merge($existing, $new);

            expect($result)->toHaveKey('a')
                ->and($result)->toHaveKey('b')
                ->and($result)->toHaveKey('c');
        });
    });

    describe('countNew()', function () {
        it('counts zero new keys when new is empty', function () {
            expect($this->service->countNew(['a' => 'A'], []))->toBe(0);
        });

        it('counts all keys when existing is empty', function () {
            expect($this->service->countNew([], ['a' => 'A', 'b' => 'B']))->toBe(2);
        });

        it('counts only keys absent from existing', function () {
            $existing = ['a' => 'A'];
            $new = ['a' => 'new', 'b' => 'B'];

            expect($this->service->countNew($existing, $new))->toBe(1);
        });

        it('counts nested missing leaf values', function () {
            $existing = ['auth' => ['login' => 'Login']];
            $new = ['auth' => ['login' => 'x', 'logout' => 'y', 'register' => 'z']];

            // login exists, logout and register are new.
            expect($this->service->countNew($existing, $new))->toBe(2);
        });

        it('counts deeply nested missing values', function () {
            $existing = [];
            $new = ['a' => ['b' => ['c' => 'v1', 'd' => 'v2']]];

            expect($this->service->countNew($existing, $new))->toBe(2);
        });
    });

    describe('countAll()', function () {
        it('counts all leaf values in a flat array', function () {
            expect($this->service->countAll(['a' => 'A', 'b' => 'B']))->toBe(2);
        });

        it('counts all leaf values in a nested array', function () {
            expect($this->service->countAll([
                'a' => ['b' => 'B', 'c' => 'C'],
                'd' => 'D',
            ]))->toBe(3);
        });

        it('returns zero for an empty array', function () {
            expect($this->service->countAll([]))->toBe(0);
        });
    });
});
