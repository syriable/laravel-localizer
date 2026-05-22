<?php

declare(strict_types=1);

use Syriable\Localizer\Data\StringKind;
use Syriable\Localizer\Support\StringClassifier;

beforeEach(function () {
    $this->classifier = new StringClassifier;
});

describe('StringClassifier — classify()', function () {
    it('classifies plain dotted lowercase strings as short keys', function () {
        expect($this->classifier->classify('pagination.next'))->toBe(StringKind::ShortKey)
            ->and($this->classifier->classify('auth.failed'))->toBe(StringKind::ShortKey)
            ->and($this->classifier->classify('messages.errors.required'))->toBe(StringKind::ShortKey);
    });

    it('classifies free text as json keys', function () {
        expect($this->classifier->classify('Welcome back'))->toBe(StringKind::JsonKey)
            ->and($this->classifier->classify('Hello, world!'))->toBe(StringKind::JsonKey)
            ->and($this->classifier->classify('A simple sentence.'))->toBe(StringKind::JsonKey);
    });

    it('classifies bare words (no dots) as json keys', function () {
        expect($this->classifier->classify('hello'))->toBe(StringKind::JsonKey)
            ->and($this->classifier->classify('pagination'))->toBe(StringKind::JsonKey);
    });

    it('classifies strings with uppercase as json keys', function () {
        expect($this->classifier->classify('Pagination.Next'))->toBe(StringKind::JsonKey)
            ->and($this->classifier->classify('auth.Failed'))->toBe(StringKind::JsonKey);
    });

    it('classifies strings with whitespace as json keys', function () {
        expect($this->classifier->classify('a.b c'))->toBe(StringKind::JsonKey)
            ->and($this->classifier->classify(' pagination.next'))->toBe(StringKind::JsonKey);
    });

    it('accepts digits, underscores, and hyphens in segments', function () {
        expect($this->classifier->classify('a1.b2'))->toBe(StringKind::ShortKey)
            ->and($this->classifier->classify('a_b.c_d'))->toBe(StringKind::ShortKey)
            ->and($this->classifier->classify('foo-bar.baz'))->toBe(StringKind::ShortKey);
    });
});

describe('StringClassifier — decomposition of plain keys', function () {
    it('decomposes pagination.next', function () {
        expect($this->classifier->packageFor('pagination.next'))->toBeNull()
            ->and($this->classifier->directoriesFor('pagination.next'))->toBe([])
            ->and($this->classifier->fileFor('pagination.next'))->toBe('pagination')
            ->and($this->classifier->keyFor('pagination.next'))->toBe('next');
    });

    it('decomposes auth.failed.attempts (multi-segment key path)', function () {
        expect($this->classifier->packageFor('auth.failed.attempts'))->toBeNull()
            ->and($this->classifier->directoriesFor('auth.failed.attempts'))->toBe([])
            ->and($this->classifier->fileFor('auth.failed.attempts'))->toBe('auth')
            ->and($this->classifier->keyFor('auth.failed.attempts'))->toBe('failed.attempts');
    });
});

describe('StringClassifier — decomposition of directory-nested keys', function () {
    it('decomposes profile/buttons.submit', function () {
        expect($this->classifier->packageFor('profile/buttons.submit'))->toBeNull()
            ->and($this->classifier->directoriesFor('profile/buttons.submit'))->toBe(['profile'])
            ->and($this->classifier->fileFor('profile/buttons.submit'))->toBe('buttons')
            ->and($this->classifier->keyFor('profile/buttons.submit'))->toBe('submit');
    });

    it('decomposes profile/button/form/icon.submit.label (deep nesting)', function () {
        $v = 'profile/button/form/icon.submit.label';

        expect($this->classifier->packageFor($v))->toBeNull()
            ->and($this->classifier->directoriesFor($v))->toBe(['profile', 'button', 'form'])
            ->and($this->classifier->fileFor($v))->toBe('icon')
            ->and($this->classifier->keyFor($v))->toBe('submit.label');
    });
});

describe('StringClassifier — decomposition of packaged keys', function () {
    it('decomposes syriable::buttons.submit (package + plain)', function () {
        expect($this->classifier->packageFor('syriable::buttons.submit'))->toBe('syriable')
            ->and($this->classifier->directoriesFor('syriable::buttons.submit'))->toBe([])
            ->and($this->classifier->fileFor('syriable::buttons.submit'))->toBe('buttons')
            ->and($this->classifier->keyFor('syriable::buttons.submit'))->toBe('submit');
    });

    it('decomposes syriable::profile/buttons.submit.label', function () {
        $v = 'syriable::profile/buttons.submit.label';

        expect($this->classifier->packageFor($v))->toBe('syriable')
            ->and($this->classifier->directoriesFor($v))->toBe(['profile'])
            ->and($this->classifier->fileFor($v))->toBe('buttons')
            ->and($this->classifier->keyFor($v))->toBe('submit.label');
    });

    it('decomposes syriable::profile/button/form/icon.submit.label (deep, packaged)', function () {
        $v = 'syriable::profile/button/form/icon.submit.label';

        expect($this->classifier->packageFor($v))->toBe('syriable')
            ->and($this->classifier->directoriesFor($v))->toBe(['profile', 'button', 'form'])
            ->and($this->classifier->fileFor($v))->toBe('icon')
            ->and($this->classifier->keyFor($v))->toBe('submit.label');
    });
});

describe('StringClassifier — malformed shapes fall through to JsonKey', function () {
    it('rejects empty directory segments', function () {
        expect($this->classifier->classify('/buttons.submit'))->toBe(StringKind::JsonKey)
            ->and($this->classifier->classify('profile//buttons.submit'))->toBe(StringKind::JsonKey);
    });

    it('rejects trailing slash with no file', function () {
        expect($this->classifier->classify('profile/'))->toBe(StringKind::JsonKey)
            ->and($this->classifier->classify('a/b/'))->toBe(StringKind::JsonKey);
    });

    it('rejects no dotted key path', function () {
        expect($this->classifier->classify('profile/buttons'))->toBe(StringKind::JsonKey)
            ->and($this->classifier->classify('syriable::buttons'))->toBe(StringKind::JsonKey);
    });

    it('rejects empty package', function () {
        expect($this->classifier->classify('::buttons.submit'))->toBe(StringKind::JsonKey);
    });

    it('rejects package with nothing else', function () {
        expect($this->classifier->classify('syriable::'))->toBe(StringKind::JsonKey);
    });

    it('rejects empty segment in package', function () {
        expect($this->classifier->classify('syriable:::buttons.submit'))->toBe(StringKind::JsonKey);
    });

    it('decomposition methods return safe defaults for JSON keys', function () {
        expect($this->classifier->packageFor('Welcome back'))->toBeNull()
            ->and($this->classifier->directoriesFor('Welcome back'))->toBe([])
            ->and($this->classifier->fileFor('Welcome back'))->toBeNull()
            ->and($this->classifier->keyFor('Welcome back'))->toBeNull();
    });
});
