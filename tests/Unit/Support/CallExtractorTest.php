<?php

declare(strict_types=1);

use Syriable\Localizer\Support\CallExtractor;

beforeEach(function () {
    $this->extractor = new CallExtractor;
});

describe('CallExtractor::extractCalls()', function () {
    it('extracts a single function call with single-quoted string', function () {
        $matches = iterator_to_array($this->extractor->extractCalls("__('hello world')", ['__']));

        expect($matches)->toHaveCount(1)
            ->and($matches[0]['value'])->toBe('hello world');
    });

    it('extracts a single function call with double-quoted string', function () {
        $matches = iterator_to_array($this->extractor->extractCalls('__("hello world")', ['__']));

        expect($matches)->toHaveCount(1)
            ->and($matches[0]['value'])->toBe('hello world');
    });

    it('extracts a backtick-quoted string', function () {
        $matches = iterator_to_array($this->extractor->extractCalls('t(`backticks`)', ['t']));

        expect($matches)->toHaveCount(1)
            ->and($matches[0]['value'])->toBe('backticks');
    });

    it('handles escaped single quotes', function () {
        $code = "__('it\\'s working')";
        $matches = iterator_to_array($this->extractor->extractCalls($code, ['__']));

        expect($matches)->toHaveCount(1)
            ->and($matches[0]['value'])->toBe("it's working");
    });

    it('handles escaped double quotes', function () {
        $code = '__("say \\"hi\\"")';
        $matches = iterator_to_array($this->extractor->extractCalls($code, ['__']));

        expect($matches)->toHaveCount(1)
            ->and($matches[0]['value'])->toBe('say "hi"');
    });

    it('handles escape sequences \\n \\t \\r \\\\', function () {
        $code = '__("line\\nfeed\\ttab\\rback\\\\slash")';
        $matches = iterator_to_array($this->extractor->extractCalls($code, ['__']));

        expect($matches[0]['value'])->toBe("line\nfeed\ttab\rback\\slash");
    });

    it('extracts multiple distinct calls', function () {
        $code = "__('first') and trans('second') and __('third')";
        $matches = iterator_to_array($this->extractor->extractCalls($code, ['__', 'trans']));

        $values = array_column($matches, 'value');

        expect($values)->toBe(['first', 'second', 'third']);
    });

    it('handles multi-line function calls with whitespace', function () {
        $code = "__(\n    'multi line'\n)";
        $matches = iterator_to_array($this->extractor->extractCalls($code, ['__']));

        expect($matches)->toHaveCount(1)
            ->and($matches[0]['value'])->toBe('multi line');
    });

    it('ignores function calls with dynamic first argument', function () {
        $code = '__($variable) and __(getKey())';
        $matches = iterator_to_array($this->extractor->extractCalls($code, ['__']));

        expect($matches)->toBe([]);
    });

    it('returns nothing when no function names are given', function () {
        $matches = iterator_to_array($this->extractor->extractCalls("__('x')", []));

        expect($matches)->toBe([]);
    });

    it('returns nothing when input has no matches', function () {
        $matches = iterator_to_array($this->extractor->extractCalls('plain text', ['__']));

        expect($matches)->toBe([]);
    });

    it('does not match function-name substrings of longer identifiers', function () {
        // `not__` and `__custom` should NOT trigger a match on `__`.
        $code = "not__('a') __custom('b') someName__('c')";
        $matches = iterator_to_array($this->extractor->extractCalls($code, ['__']));

        expect($matches)->toBe([]);
    });

    it('does not match method calls preceded by a dot (this.t, router.t, console.t)', function () {
        $code = "this.t('method_call'); router.t('route'); console.t('debug'); t('real_key');";
        $matches = iterator_to_array($this->extractor->extractCalls($code, ['t']));

        $values = array_column($matches, 'value');

        expect($matches)->toHaveCount(1)
            ->and($values)->toBe(['real_key']);
    });

    it('does not match dot-prefixed short names like obj.tc()', function () {
        $code = "obj.tc('should_skip'); tc('should_match');";
        $matches = iterator_to_array($this->extractor->extractCalls($code, ['tc']));

        $values = array_column($matches, 'value');

        expect($matches)->toHaveCount(1)
            ->and($values)->toBe(['should_match']);
    });

    it('does not match chained method calls like i18n.global.t()', function () {
        // i18n.global.t should be in FUNCTIONS as a literal name, not trigger on bare `t`.
        $code = "i18n.global.t('chained'); t('standalone');";
        $matches = iterator_to_array($this->extractor->extractCalls($code, ['t']));

        $values = array_column($matches, 'value');

        expect($matches)->toHaveCount(1)
            ->and($values)->toBe(['standalone']);
    });

    it('matches function-name with no whitespace between name and paren', function () {
        $matches = iterator_to_array($this->extractor->extractCalls("trans('x')", ['trans']));

        expect($matches)->toHaveCount(1)
            ->and($matches[0]['value'])->toBe('x');
    });

    it('matches function-name with whitespace between name and paren', function () {
        $matches = iterator_to_array($this->extractor->extractCalls("trans  ('x')", ['trans']));

        expect($matches)->toHaveCount(1);
    });

    it('handles namespace-style function names like Lang::get', function () {
        $matches = iterator_to_array($this->extractor->extractCalls(
            "Lang::get('auth.failed')",
            ['Lang::get'],
        ));

        expect($matches)->toHaveCount(1)
            ->and($matches[0]['value'])->toBe('auth.failed');
    });

    it('handles property-style names like $t and i18n.t', function () {
        $matches = iterator_to_array($this->extractor->extractCalls(
            "i18n.t('hello') and \$t('world')",
            ['$t', 'i18n.t'],
        ));

        $values = array_column($matches, 'value');

        expect($values)->toContain('hello')
            ->and($values)->toContain('world');
    });

    it('returns the offset of the opening quote', function () {
        $code = "    __('greeting')";
        $matches = iterator_to_array($this->extractor->extractCalls($code, ['__']));

        // Opening quote is at position 7 (after "    __(").
        expect($matches[0]['offset'])->toBe(7);
    });

    it('skips calls where first argument is not a quoted string', function () {
        $matches = iterator_to_array($this->extractor->extractCalls(
            '__(123) and __([])',
            ['__'],
        ));

        expect($matches)->toBe([]);
    });

    it('does not stop on the first failure', function () {
        $code = '__($dynamic) and __("recovers")';
        $matches = iterator_to_array($this->extractor->extractCalls($code, ['__']));

        expect($matches)->toHaveCount(1)
            ->and($matches[0]['value'])->toBe('recovers');
    });

    it('handles unterminated strings safely (returns nothing for that match)', function () {
        $code = "__('unterminated";
        $matches = iterator_to_array($this->extractor->extractCalls($code, ['__']));

        expect($matches)->toBe([]);
    });
});

describe('CallExtractor::lineFor()', function () {
    it('returns 1 for the start of the content', function () {
        expect($this->extractor->lineFor('first line', 0))->toBe(1);
    });

    it('counts newlines correctly', function () {
        $code = "line1\nline2\nline3";
        // 'line3' starts at offset 12.
        expect($this->extractor->lineFor($code, 12))->toBe(3);
    });

    it('handles Windows-style line endings as additional newlines', function () {
        $code = "line1\r\nline2";
        // 'line2' starts at offset 7.
        expect($this->extractor->lineFor($code, 7))->toBe(2);
    });
});
