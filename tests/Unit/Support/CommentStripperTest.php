<?php

declare(strict_types=1);

use Syriable\Localizer\Support\CommentStripper;

beforeEach(function () {
    $this->stripper = new CommentStripper;
});

describe('CommentStripper — PHP single-line comments (//)', function () {
    it('strips a standalone // comment line', function () {
        $result = $this->stripper->strip("<?php\n// __('ignored.key')\necho 'hello';");

        expect($result)->not->toContain('__')
            ->and($result)->toContain("echo 'hello'");
    });

    it('strips a trailing // comment on a code line', function () {
        $result = $this->stripper->strip("<?php\n\$x = 1; // __('ignored') side note\n\$y = 2;");

        expect($result)->not->toContain('__')
            ->and($result)->toContain('$x = 1;')
            ->and($result)->toContain('$y = 2;');
    });

    it('preserves text on the next line after a // comment', function () {
        $input = "// comment\nnext line";
        $result = $this->stripper->strip($input);

        expect($result)->toContain('next line');
    });

    it('preserves the newline character that terminates the // comment', function () {
        $result = $this->stripper->strip("// comment\ncode");

        expect(substr_count($result, "\n"))->toBe(1);
    });

    it('strips a // comment with no following newline (end of file)', function () {
        $result = $this->stripper->strip('code; // trailing');

        expect($result)->toContain('code;')
            ->and($result)->not->toContain('trailing');
    });

    it('does not strip // inside a single-quoted string', function () {
        $result = $this->stripper->strip("'http://example.com'");

        expect($result)->toBe("'http://example.com'");
    });

    it('does not strip // inside a double-quoted string', function () {
        $result = $this->stripper->strip('"http://example.com"');

        expect($result)->toBe('"http://example.com"');
    });
});

describe('CommentStripper — PHP hash comments (#)', function () {
    it('strips a standalone # comment line', function () {
        $result = $this->stripper->strip("<?php\n# __('ignored.key')\necho 'real';");

        expect($result)->not->toContain('__')
            ->and($result)->toContain("echo 'real'");
    });

    it('strips a trailing # comment on a code line', function () {
        $result = $this->stripper->strip("\$x = 1; # __('ignored')");

        expect($result)->not->toContain('__')
            ->and($result)->toContain('$x = 1;');
    });

    it('preserves the newline that terminates a # comment', function () {
        $result = $this->stripper->strip("# comment\ncode");

        expect(substr_count($result, "\n"))->toBe(1)
            ->and($result)->toContain('code');
    });

    it('does NOT strip #[ (PHP 8 attribute syntax)', function () {
        $result = $this->stripper->strip("#[Attribute]\nclass Foo {}");

        expect($result)->toContain('#[Attribute]')
            ->and($result)->toContain('class Foo {}');
    });

    it('does not strip # inside a single-quoted string', function () {
        $result = $this->stripper->strip("'color: #ff0000'");

        expect($result)->toBe("'color: #ff0000'");
    });
});

describe('CommentStripper — PHP block comments (/* ... */)', function () {
    it('strips a single-line block comment', function () {
        $result = $this->stripper->strip("before /* __('ignored') */ after");

        expect($result)->not->toContain('__')
            ->and($result)->toContain('before')
            ->and($result)->toContain('after');
    });

    it('strips a multi-line block comment', function () {
        $input = <<<'PHP'
before
/*
 * __('ignored.key')
 * trans('also.ignored')
 */
after
PHP;
        $result = $this->stripper->strip($input);

        expect($result)->not->toContain('__')
            ->and($result)->not->toContain('trans')
            ->and($result)->toContain('before')
            ->and($result)->toContain('after');
    });

    it('preserves newlines inside a stripped block comment', function () {
        $input = "before\n/* line1\nline2 */\nafter";
        $result = $this->stripper->strip($input);

        expect(substr_count($result, "\n"))->toBe(substr_count($input, "\n"));
    });

    it('strips a docblock comment', function () {
        $input = <<<'PHP'
/**
 * @uses __('should.be.ignored')
 */
function foo() {}
PHP;
        $result = $this->stripper->strip($input);

        expect($result)->not->toContain('__')
            ->and($result)->toContain('function foo()');
    });

    it('does not strip /* inside a string literal', function () {
        $result = $this->stripper->strip("'/* not a comment */'");

        expect($result)->toBe("'/* not a comment */'");
    });
});

describe('CommentStripper — Blade comments ({{-- ... --}})', function () {
    it('strips a Blade comment containing a translation call', function () {
        $result = $this->stripper->strip("{{-- __('ignored.key') --}}\n{{ __('real.key') }}");

        expect($result)->not->toContain("'ignored.key'")
            ->and($result)->toContain("'real.key'");
    });

    it('strips a multi-line Blade comment', function () {
        $input = "{{--\n  __('ignored')\n  trans('also.ignored')\n--}}\ncode";
        $result = $this->stripper->strip($input);

        expect($result)->not->toContain('__')
            ->and($result)->not->toContain('trans')
            ->and($result)->toContain('code');
    });

    it('preserves newlines inside a stripped Blade comment', function () {
        $input = "{{--\nline1\nline2\n--}}\nafter";
        $result = $this->stripper->strip($input);

        expect(substr_count($result, "\n"))->toBe(substr_count($input, "\n"));
    });

    it('does not strip {{-- inside a string literal', function () {
        $result = $this->stripper->strip("'{{-- not stripped --}}'");

        expect($result)->toBe("'{{-- not stripped --}}'");
    });

    it('strips multiple Blade comments in one file', function () {
        $input = '{{-- first --}} real {{-- second --}}';
        $result = $this->stripper->strip($input);

        expect($result)->not->toContain('first')
            ->and($result)->not->toContain('second')
            ->and($result)->toContain('real');
    });
});

describe('CommentStripper — HTML comments (<!-- ... -->)', function () {
    it('strips an HTML comment containing a translation call', function () {
        $result = $this->stripper->strip("<!-- __('ignored') -->\n<p>{{ __('real') }}</p>");

        expect($result)->not->toContain("'ignored'")
            ->and($result)->toContain("'real'");
    });

    it('strips a multi-line HTML comment', function () {
        $input = "<!--\n  __('ignored')\n  trans('also')\n-->\n<real />";
        $result = $this->stripper->strip($input);

        expect($result)->not->toContain('__')
            ->and($result)->not->toContain('trans')
            ->and($result)->toContain('<real />');
    });

    it('preserves newlines inside a stripped HTML comment', function () {
        $input = "<!--\nline1\nline2\n-->\nafter";
        $result = $this->stripper->strip($input);

        expect(substr_count($result, "\n"))->toBe(substr_count($input, "\n"));
    });

    it('does not strip <!-- inside a string literal', function () {
        $result = $this->stripper->strip("'<!-- not stripped -->'");

        expect($result)->toBe("'<!-- not stripped -->'");
    });
});

describe('CommentStripper — string literal protection', function () {
    it('preserves comment-like content in single-quoted strings', function () {
        $input = "\$a = '// not a comment'; \$b = 1;";
        $result = $this->stripper->strip($input);

        expect($result)->toBe($input);
    });

    it('preserves comment-like content in double-quoted strings', function () {
        $input = '$a = "/* not a comment */"; $b = 1;';
        $result = $this->stripper->strip($input);

        expect($result)->toBe($input);
    });

    it('preserves comment-like content in backtick strings', function () {
        $input = '`// not a comment`';
        $result = $this->stripper->strip($input);

        expect($result)->toBe($input);
    });

    it('handles escaped quotes inside strings', function () {
        $input = "'it\\'s // not a comment' real // comment";
        $result = $this->stripper->strip($input);

        expect($result)->toContain("'it\\'s // not a comment'")
            ->and($result)->not->toContain('real // comment');
    });

    it('handles a string immediately followed by a comment', function () {
        $input = "'value'// comment";
        $result = $this->stripper->strip($input);

        expect($result)->toContain("'value'")
            ->and($result)->not->toContain('comment');
    });
});

describe('CommentStripper — offset and line-number stability', function () {
    it('produces output of identical byte length to the input', function () {
        $input = "<?php\n// comment\ncode;\n/* block */\nmore;";
        $result = $this->stripper->strip($input);

        expect(strlen($result))->toBe(strlen($input));
    });

    it('preserves the total newline count', function () {
        $input = "line1\n// comment\nline3\n/* multi\nline */\nline6";
        $result = $this->stripper->strip($input);

        expect(substr_count($result, "\n"))->toBe(substr_count($input, "\n"));
    });

    it('leaves content with no comments unchanged', function () {
        $input = "<?php\n\$x = __('real.key');\n";
        $result = $this->stripper->strip($input);

        expect($result)->toBe($input);
    });
});

describe('CommentStripper — real-world extraction scenarios', function () {
    it('ignores translation keys in PHP // comments but finds real ones', function () {
        $input = <<<'PHP'
<?php
// __('auth.ignored')
$label = __('auth.real');
PHP;
        $result = $this->stripper->strip($input);

        expect($result)->not->toContain("'auth.ignored'")
            ->and($result)->toContain("'auth.real'");
    });

    it('ignores translation keys in PHP # comments but finds real ones', function () {
        $input = <<<'PHP'
<?php
# __('pagination.ignored')
$next = __('pagination.next');
PHP;
        $result = $this->stripper->strip($input);

        expect($result)->not->toContain("'pagination.ignored'")
            ->and($result)->toContain("'pagination.next'");
    });

    it('ignores translation keys in block comments but finds real ones', function () {
        $input = <<<'PHP'
<?php
/*
 * __('block.ignored')
 */
echo __('real.key');
PHP;
        $result = $this->stripper->strip($input);

        expect($result)->not->toContain("'block.ignored'")
            ->and($result)->toContain("'real.key'");
    });

    it('ignores translation keys in Blade comments but finds real ones', function () {
        $input = "{{-- __('blade.ignored') --}}\n{{ __('blade.real') }}";
        $result = $this->stripper->strip($input);

        expect($result)->not->toContain("'blade.ignored'")
            ->and($result)->toContain("'blade.real'");
    });

    it('ignores translation keys in HTML comments but finds real ones', function () {
        $input = "<!-- __('html.ignored') -->\n{{ __('html.real') }}";
        $result = $this->stripper->strip($input);

        expect($result)->not->toContain("'html.ignored'")
            ->and($result)->toContain("'html.real'");
    });
});
