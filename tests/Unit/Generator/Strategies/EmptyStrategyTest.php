<?php

declare(strict_types=1);

use Syriable\Localizer\Generator\Strategies\EmptyStrategy;

describe('EmptyStrategy', function () {
    beforeEach(function () {
        $this->strategy = new EmptyStrategy;
    });

    it('has the name "empty"', function () {
        expect($this->strategy->name())->toBe('empty');
    });

    it('always returns an empty string regardless of key', function () {
        expect($this->strategy->generate('submit.label'))->toBe('');
        expect($this->strategy->generate('auth.login.failed'))->toBe('');
        expect($this->strategy->generate(''))->toBe('');
        expect($this->strategy->generate('anything'))->toBe('');
    });
});
