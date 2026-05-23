<?php

declare(strict_types=1);

use Syriable\Localizer\Generator\Strategies\KeyStrategy;

describe('KeyStrategy', function () {
    beforeEach(function () {
        $this->strategy = new KeyStrategy;
    });

    it('has the name "key"', function () {
        expect($this->strategy->name())->toBe('key');
    });

    it('returns the key unchanged', function () {
        expect($this->strategy->generate('submit.label'))->toBe('submit.label');
        expect($this->strategy->generate('auth.login.failed'))->toBe('auth.login.failed');
        expect($this->strategy->generate('pagination.next'))->toBe('pagination.next');
    });

    it('returns an empty string for an empty key', function () {
        expect($this->strategy->generate(''))->toBe('');
    });
});
