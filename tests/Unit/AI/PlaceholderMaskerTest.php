<?php

declare(strict_types=1);

use Syriable\Localizer\AI\PlaceholderMasker;

describe('PlaceholderMasker', function () {
    beforeEach(function () {
        $this->masker = new PlaceholderMasker;
    });

    it('leaves text without placeholders unchanged', function () {
        $result = $this->masker->mask('Hello world');

        expect($result['masked'])->toBe('Hello world')
            ->and($result['map'])->toBe([]);
    });

    it('masks a single placeholder', function () {
        $result = $this->masker->mask('Hello, :name!');

        expect($result['masked'])->toBe('Hello, {{P0}}!')
            ->and($result['map'])->toBe(['{{P0}}' => ':name']);
    });

    it('masks multiple placeholders in order', function () {
        $result = $this->masker->mask('Hi :name, you have :count messages from :sender.');

        expect($result['masked'])->toBe('Hi {{P0}}, you have {{P1}} messages from {{P2}}.')
            ->and($result['map'])->toBe([
                '{{P0}}' => ':name',
                '{{P1}}' => ':count',
                '{{P2}}' => ':sender',
            ]);
    });

    it('unmasks a text with no map entries unchanged', function () {
        $result = $this->masker->unmask('Bonjour monde', []);

        expect($result)->toBe('Bonjour monde');
    });

    it('restores original placeholders after unmask', function () {
        ['masked' => $masked, 'map' => $map] = $this->masker->mask('Hello, :name!');

        $translated = str_replace('Hello', 'Bonjour', $masked);
        $restored = $this->masker->unmask($translated, $map);

        expect($restored)->toBe('Bonjour, :name!');
    });

    it('round-trips multi-placeholder text faithfully', function () {
        $original = 'Welcome back, :name! Your score is :score out of :total.';

        ['masked' => $masked, 'map' => $map] = $this->masker->mask($original);

        expect($masked)->not->toContain(':name')
            ->and($masked)->not->toContain(':score')
            ->and($masked)->not->toContain(':total');

        $restored = $this->masker->unmask($masked, $map);

        expect($restored)->toBe($original);
    });

    it('does not mask dot-notation keys without colons', function () {
        $result = $this->masker->mask('auth.login.failed');

        expect($result['masked'])->toBe('auth.login.failed')
            ->and($result['map'])->toBe([]);
    });

    it('handles underscored placeholder names', function () {
        $result = $this->masker->mask('Dear :first_name :last_name');

        expect($result['masked'])->toBe('Dear {{P0}} {{P1}}')
            ->and($result['map'])->toBe([
                '{{P0}}' => ':first_name',
                '{{P1}}' => ':last_name',
            ]);
    });
});
