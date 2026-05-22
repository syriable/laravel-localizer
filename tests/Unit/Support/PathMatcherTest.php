<?php

declare(strict_types=1);

use Syriable\Localizer\Support\PathMatcher;

beforeEach(function () {
    $this->matcher = new PathMatcher;
});

describe('PathMatcher::matches()', function () {
    it('matches exact strings', function () {
        expect($this->matcher->matches('/app/User.php', '/app/User.php'))->toBeTrue();
    });

    it('matches single-segment wildcards', function () {
        expect($this->matcher->matches('/app/User.php', '/app/*.php'))->toBeTrue()
            ->and($this->matcher->matches('/app/sub/User.php', '/app/*.php'))->toBeFalse();
    });

    it('matches recursive ** wildcards', function () {
        expect($this->matcher->matches('/app/sub/User.php', '/app/**/*.php'))->toBeTrue()
            ->and($this->matcher->matches('/app/a/b/c/User.php', '/app/**/*.php'))->toBeTrue();
    });

    it('matches ** at any position', function () {
        expect($this->matcher->matches('/x/node_modules/y.js', '**/node_modules/**'))->toBeTrue()
            ->and($this->matcher->matches('/node_modules/y.js', '**/node_modules/**'))->toBeTrue()
            ->and($this->matcher->matches('/x/y.js', '**/node_modules/**'))->toBeFalse();
    });

    it('matches single-character ? wildcards', function () {
        expect($this->matcher->matches('/app/a.php', '/app/?.php'))->toBeTrue()
            ->and($this->matcher->matches('/app/ab.php', '/app/?.php'))->toBeFalse();
    });

    it('escapes regex metacharacters literally', function () {
        expect($this->matcher->matches('/app/file.bar', '/app/file.bar'))->toBeTrue()
            // The literal-dot test: matching '.' should not match 'x'.
            ->and($this->matcher->matches('/app/filexbar', '/app/file.bar'))->toBeFalse();
    });

    it('normalizes backslashes to forward slashes', function () {
        expect($this->matcher->matches('C:\\app\\file.php', '**/file.php'))->toBeTrue();
    });
});

describe('PathMatcher::matchesAny()', function () {
    it('returns true when any pattern matches', function () {
        expect($this->matcher->matchesAny('/app/node_modules/x.js', [
            '**/vendor/**',
            '**/node_modules/**',
        ]))->toBeTrue();
    });

    it('returns false when no pattern matches', function () {
        expect($this->matcher->matchesAny('/app/source.php', [
            '**/vendor/**',
            '**/node_modules/**',
        ]))->toBeFalse();
    });

    it('returns false for empty pattern list', function () {
        expect($this->matcher->matchesAny('/any/path', []))->toBeFalse();
    });
});
