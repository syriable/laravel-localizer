<?php

declare(strict_types=1);

use Syriable\Localizer\Generator\TranslationPhpRenderer;

describe('TranslationPhpRenderer', function () {
    beforeEach(function () {
        $this->renderer = new TranslationPhpRenderer;
    });

    it('renders an empty array', function () {
        $output = $this->renderer->render([]);

        expect($output)->toBe("<?php\n\ndeclare(strict_types=1);\n\nreturn [\n];\n");
    });

    it('renders a flat key-value array', function () {
        $output = $this->renderer->render(['next' => 'Next', 'prev' => 'Previous']);

        expect($output)->toContain("    'next' => 'Next',")
            ->and($output)->toContain("    'prev' => 'Previous',");
    });

    it('renders a nested array with correct indentation', function () {
        $output = $this->renderer->render(['auth' => ['login' => 'Login', 'logout' => 'Logout']]);

        expect($output)->toContain("    'auth' => [")
            ->and($output)->toContain("        'login' => 'Login',")
            ->and($output)->toContain("        'logout' => 'Logout',")
            ->and($output)->toContain('    ],');
    });

    it('starts with the PHP opening tag and strict declaration', function () {
        $output = $this->renderer->render(['a' => 'b']);

        expect($output)->toStartWith("<?php\n\ndeclare(strict_types=1);\n\nreturn [");
    });

    it('ends with a closing bracket and newline', function () {
        $output = $this->renderer->render(['a' => 'b']);

        expect($output)->toEndWith("];\n");
    });

    it('escapes single quotes in values', function () {
        $output = $this->renderer->render(['key' => "it's alive"]);

        expect($output)->toContain("'it\\'s alive'");
    });

    it('escapes single quotes in keys', function () {
        $output = $this->renderer->render(["it's" => 'value']);

        expect($output)->toContain("'it\\'s'");
    });

    it('escapes backslashes in values', function () {
        $output = $this->renderer->render(['key' => 'path\\to\\file']);

        expect($output)->toContain("'path\\\\to\\\\file'");
    });

    it('renders deeply nested arrays', function () {
        $data = ['a' => ['b' => ['c' => 'deep']]];
        $output = $this->renderer->render($data);

        expect($output)->toContain("    'a' => [")
            ->and($output)->toContain("        'b' => [")
            ->and($output)->toContain("            'c' => 'deep',");
    });

    it('produces valid PHP that can be eval-ed back', function () {
        $original = [
            'welcome' => 'Welcome',
            'auth' => ['login' => 'Login', 'logout' => 'Logout'],
        ];

        $output = $this->renderer->render($original);
        // Strip <?php opening tag for eval()
        $evalable = str_replace('<?php', '', $output);

        /** @var array<string, mixed> $result */
        $result = eval($evalable);

        expect($result)->toBe($original);
    });

    it('renders null values', function () {
        $output = $this->renderer->render(['key' => null]);

        expect($output)->toContain("'key' => null,");
    });

    it('renders boolean values', function () {
        $output = $this->renderer->render(['a' => true, 'b' => false]);

        expect($output)->toContain("'a' => true,")
            ->and($output)->toContain("'b' => false,");
    });

    it('renders integer values', function () {
        $output = $this->renderer->render(['count' => 42]);

        expect($output)->toContain("'count' => 42,");
    });
});
