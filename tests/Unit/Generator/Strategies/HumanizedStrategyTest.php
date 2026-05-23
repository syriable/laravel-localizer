<?php

declare(strict_types=1);

use Syriable\Localizer\Generator\Strategies\HumanizedStrategy;

describe('HumanizedStrategy', function () {
    beforeEach(function () {
        $this->strategy = new HumanizedStrategy;
    });

    it('has the name "humanized"', function () {
        expect($this->strategy->name())->toBe('humanized');
    });

    it('converts a bare word key to ucfirst', function () {
        expect($this->strategy->generate('submit'))->toBe('Submit');
    });

    it('uses only the last dot segment', function () {
        expect($this->strategy->generate('submit.label'))->toBe('Label');
        expect($this->strategy->generate('auth.login.failed'))->toBe('Failed');
    });

    it('replaces underscores with spaces', function () {
        expect($this->strategy->generate('submit_form'))->toBe('Submit form');
    });

    it('replaces hyphens with spaces', function () {
        expect($this->strategy->generate('login-failed'))->toBe('Login failed');
    });

    it('handles deeply nested keys', function () {
        expect($this->strategy->generate('a.b.c.d.my_key'))->toBe('My key');
    });

    it('returns the raw key when empty', function () {
        expect($this->strategy->generate(''))->toBe('');
    });
});
