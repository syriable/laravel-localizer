<?php

declare(strict_types=1);

use Syriable\Localizer\Analysis\PlaceholderAnalysis;
use Syriable\Localizer\Analysis\PlaceholderType;
use Syriable\Localizer\Analysis\TranslationCallAnalysis;
use Syriable\Localizer\Data\SourceLocation;
use Syriable\Localizer\Generator\Strategies\HumanizedStrategy;

describe('HumanizedStrategy — basic humanisation', function () {
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

    it('returns empty string for empty input', function () {
        expect($this->strategy->generate(''))->toBe('');
    });
});

describe('HumanizedStrategy — placeholder-aware generation via AnalysisAwareStrategy', function () {
    /**
     * Helper: build a minimal TranslationCallAnalysis with placeholder names.
     *
     * @param list<string> $placeholderNames e.g. ['name', 'count']
     */
    function makeAnalysis(string $key, array $placeholderNames): TranslationCallAnalysis
    {
        $placeholders = array_map(
            static fn (string $name): PlaceholderAnalysis => new PlaceholderAnalysis(
                placeholder: ':'.$name,
                source: '$'.$name,
                type: PlaceholderType::Variable,
                structure: ['name' => '$'.$name],
            ),
            $placeholderNames,
        );

        return new TranslationCallAnalysis(
            key: $key,
            location: new SourceLocation('/tmp/test.php', 1),
            functionName: '__',
            placeholders: $placeholders,
            langExample: [],
        );
    }

    it('returns a new instance from withAnalysis() without mutating the original', function () {
        $base = new HumanizedStrategy;
        $analysed = $base->withAnalysis([]);

        expect($analysed)->not->toBe($base);
    });

    it('appends a single placeholder token to the humanised base', function () {
        $strategy = (new HumanizedStrategy)->withAnalysis([
            'actions.send' => makeAnalysis('actions.send', ['label']),
        ]);

        expect($strategy->generate('actions.send'))->toBe('Send :label');
    });

    it('appends multiple placeholder tokens separated by spaces', function () {
        $strategy = (new HumanizedStrategy)->withAnalysis([
            'messages.welcome' => makeAnalysis('messages.welcome', ['name', 'count']),
        ]);

        expect($strategy->generate('messages.welcome'))->toBe('Welcome :name :count');
    });

    it('falls back to plain humanisation when no analysis is registered for the key', function () {
        $strategy = (new HumanizedStrategy)->withAnalysis([
            'other.key' => makeAnalysis('other.key', ['x']),
        ]);

        expect($strategy->generate('actions.send'))->toBe('Send');
    });

    it('falls back to plain humanisation when analysis has no placeholders', function () {
        $strategy = (new HumanizedStrategy)->withAnalysis([
            'actions.send' => makeAnalysis('actions.send', []),
        ]);

        expect($strategy->generate('actions.send'))->toBe('Send');
    });

    it('produces correct output for the canonical __() + array form', function () {
        // Simulates: __('actions.send', ['label' => $email])
        $strategy = (new HumanizedStrategy)->withAnalysis([
            'actions.send' => makeAnalysis('actions.send', ['label']),
        ]);

        // Must NOT be 'Send email' — the value in the array is irrelevant;
        // the placeholder NAME drives the generated token.
        expect($strategy->generate('actions.send'))->toBe('Send :label');
    });

    it('uses the name() method correctly after withAnalysis()', function () {
        $strategy = (new HumanizedStrategy)->withAnalysis([]);

        expect($strategy->name())->toBe('humanized');
    });

    it('handles JsonKey strings (no dot segments) with placeholders', function () {
        $strategy = (new HumanizedStrategy)->withAnalysis([
            'Welcome back' => makeAnalysis('Welcome back', ['name']),
        ]);

        // For a JsonKey ('Welcome back'), the last segment IS the whole string.
        expect($strategy->generate('Welcome back'))->toBe('Welcome back :name');
    });
});

describe('HumanizedStrategy — comment-stripped placeholder extraction integration', function () {
    it('does not pick up placeholders from commented-out call sites', function () {
        // Even if a comment has __('key', ['x' => $y]) in the source,
        // the comment stripper should prevent that from polluting analyses.
        // This test ensures the strategy itself honours only the analyses array
        // it receives — if no analysis is passed, no placeholders appear.
        $strategy = new HumanizedStrategy; // no analyses

        expect($strategy->generate('actions.send'))->toBe('Send');
    });
});
