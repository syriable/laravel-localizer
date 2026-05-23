<?php

declare(strict_types=1);

use Syriable\Localizer\Data\StringKind;
use Syriable\Localizer\Generator\Strategies\HumanizedStrategy;
use Syriable\Localizer\Generator\Strategies\KeyStrategy;
use Syriable\Localizer\Generator\TranslationArrayBuilder;

describe('TranslationArrayBuilder', function () {
    beforeEach(function () {
        $this->builder = new TranslationArrayBuilder;
        $this->strategy = new KeyStrategy;
    });

    it('returns an empty array when given no strings', function () {
        expect($this->builder->build([], $this->strategy))->toBe([]);
    });

    it('builds a flat key from a single-segment key', function () {
        $string = makeExtractedString('pagination.next', StringKind::ShortKey);

        $result = $this->builder->build([$string], $this->strategy);

        expect($result)->toBe(['next' => 'next']);
    });

    it('builds a nested array from a two-segment key', function () {
        $string = makeExtractedString('buttons.submit.label', StringKind::ShortKey);

        $result = $this->builder->build([$string], $this->strategy);

        expect($result)->toBe(['submit' => ['label' => 'submit.label']]);
    });

    it('builds deeply nested arrays', function () {
        $string = makeExtractedString('profile/form.a.b.c.d', StringKind::ShortKey);

        $result = $this->builder->build([$string], $this->strategy);

        expect($result)->toBe(['a' => ['b' => ['c' => ['d' => 'a.b.c.d']]]]);
    });

    it('merges multiple keys into the same tree', function () {
        $strings = [
            makeExtractedString('auth.login', StringKind::ShortKey),
            makeExtractedString('auth.logout', StringKind::ShortKey),
            makeExtractedString('auth.register', StringKind::ShortKey),
        ];

        $result = $this->builder->build($strings, $this->strategy);

        expect($result)->toBe([
            'login' => 'login',
            'logout' => 'logout',
            'register' => 'register',
        ]);
    });

    it('merges mixed-depth keys into the same tree', function () {
        $strings = [
            makeExtractedString('buttons.submit.label', StringKind::ShortKey),
            makeExtractedString('buttons.cancel', StringKind::ShortKey),
        ];

        $result = $this->builder->build($strings, $this->strategy);

        expect($result)->toBe([
            'submit' => ['label' => 'submit.label'],
            'cancel' => 'cancel',
        ]);
    });

    it('silently skips json-key strings', function () {
        $jsonKey = makeExtractedString('Welcome back');
        $shortKey = makeExtractedString('pagination.next', StringKind::ShortKey);

        $result = $this->builder->build([$jsonKey, $shortKey], $this->strategy);

        expect($result)->toBe(['next' => 'next']);
    });

    it('applies the supplied strategy for value generation', function () {
        $string = makeExtractedString('buttons.submit_form', StringKind::ShortKey);

        $humanized = new HumanizedStrategy;
        $result = $this->builder->build([$string], $humanized);

        expect($result)->toBe(['submit_form' => 'Submit form']);
    });

    it('deduplicates by last-write-wins when duplicate keys appear', function () {
        $a = makeExtractedString('pagination.next', StringKind::ShortKey);
        $b = makeExtractedString('pagination.next', StringKind::ShortKey);

        $result = $this->builder->build([$a, $b], $this->strategy);

        expect($result)->toHaveCount(1)
            ->and($result['next'])->toBe('next');
    });
});
