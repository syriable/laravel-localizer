<?php

declare(strict_types=1);

use Syriable\Localizer\Data\ExtractedString;
use Syriable\Localizer\Data\SourceLocation;
use Syriable\Localizer\Data\StringKind;

describe('ExtractedString — construction', function () {
    it('constructs a JSON key with default empty decomposition', function () {
        $string = new ExtractedString(
            value: 'Welcome back',
            kind: StringKind::JsonKey,
            extractor: 'blade',
            location: new SourceLocation('/x', 1),
        );

        expect($string->value)->toBe('Welcome back')
            ->and($string->kind)->toBe(StringKind::JsonKey)
            ->and($string->package)->toBeNull()
            ->and($string->directories)->toBe([])
            ->and($string->file)->toBeNull()
            ->and($string->key)->toBeNull();
    });

    it('constructs a plain short key', function () {
        $string = new ExtractedString(
            value: 'pagination.next',
            kind: StringKind::ShortKey,
            extractor: 'blade',
            location: new SourceLocation('/x', 1),
            file: 'pagination',
            key: 'next',
        );

        expect($string->package)->toBeNull()
            ->and($string->directories)->toBe([])
            ->and($string->file)->toBe('pagination')
            ->and($string->key)->toBe('next');
    });

    it('constructs a nested short key with directories', function () {
        $string = new ExtractedString(
            value: 'profile/button/form/icon.submit.label',
            kind: StringKind::ShortKey,
            extractor: 'blade',
            location: new SourceLocation('/x', 1),
            directories: ['profile', 'button', 'form'],
            file: 'icon',
            key: 'submit.label',
        );

        expect($string->directories)->toBe(['profile', 'button', 'form'])
            ->and($string->file)->toBe('icon')
            ->and($string->key)->toBe('submit.label');
    });

    it('constructs a packaged short key', function () {
        $string = new ExtractedString(
            value: 'syriable::profile/buttons.submit.label',
            kind: StringKind::ShortKey,
            extractor: 'blade',
            location: new SourceLocation('/x', 1),
            package: 'syriable',
            directories: ['profile'],
            file: 'buttons',
            key: 'submit.label',
        );

        expect($string->package)->toBe('syriable')
            ->and($string->directories)->toBe(['profile'])
            ->and($string->file)->toBe('buttons')
            ->and($string->key)->toBe('submit.label');
    });
});

describe('ExtractedString — invariants', function () {
    it('rejects an empty value', function () {
        expect(fn () => new ExtractedString('', StringKind::JsonKey, 'blade', new SourceLocation('/x', 1)))
            ->toThrow(InvalidArgumentException::class, 'value cannot be empty');
    });

    it('rejects an empty extractor', function () {
        expect(fn () => new ExtractedString('hi', StringKind::JsonKey, '', new SourceLocation('/x', 1)))
            ->toThrow(InvalidArgumentException::class, 'extractor name cannot be empty');
    });

    it('requires file on ShortKey', function () {
        expect(fn () => new ExtractedString(
            value: 'pagination.next',
            kind: StringKind::ShortKey,
            extractor: 'blade',
            location: new SourceLocation('/x', 1),
            file: null,
            key: 'next',
        ))->toThrow(InvalidArgumentException::class, 'ShortKey must have a non-null file');
    });

    it('requires key on ShortKey', function () {
        expect(fn () => new ExtractedString(
            value: 'pagination.next',
            kind: StringKind::ShortKey,
            extractor: 'blade',
            location: new SourceLocation('/x', 1),
            file: 'pagination',
            key: null,
        ))->toThrow(InvalidArgumentException::class, 'ShortKey must have a non-null key');
    });

    it('rejects package on JsonKey', function () {
        expect(fn () => new ExtractedString(
            value: 'Welcome back',
            kind: StringKind::JsonKey,
            extractor: 'blade',
            location: new SourceLocation('/x', 1),
            package: 'syriable',
        ))->toThrow(InvalidArgumentException::class, 'JsonKey must have a null package');
    });

    it('rejects directories on JsonKey', function () {
        expect(fn () => new ExtractedString(
            value: 'Welcome back',
            kind: StringKind::JsonKey,
            extractor: 'blade',
            location: new SourceLocation('/x', 1),
            directories: ['x'],
        ))->toThrow(InvalidArgumentException::class, 'JsonKey must have an empty directories list');
    });

    it('rejects file on JsonKey', function () {
        expect(fn () => new ExtractedString(
            value: 'Welcome back',
            kind: StringKind::JsonKey,
            extractor: 'blade',
            location: new SourceLocation('/x', 1),
            file: 'x',
        ))->toThrow(InvalidArgumentException::class, 'JsonKey must have a null file');
    });

    it('rejects key on JsonKey', function () {
        expect(fn () => new ExtractedString(
            value: 'Welcome back',
            kind: StringKind::JsonKey,
            extractor: 'blade',
            location: new SourceLocation('/x', 1),
            key: 'x',
        ))->toThrow(InvalidArgumentException::class, 'JsonKey must have a null key');
    });

    it('rejects empty directory segments', function () {
        expect(fn () => new ExtractedString(
            value: 'profile/buttons.x',
            kind: StringKind::ShortKey,
            extractor: 'blade',
            location: new SourceLocation('/x', 1),
            directories: ['profile', ''],
            file: 'buttons',
            key: 'x',
        ))->toThrow(InvalidArgumentException::class, 'directories must be a list of non-empty strings');
    });
});

describe('ExtractedString — fingerprint', function () {
    it('produces the same fingerprint for identical values regardless of location', function () {
        $a = new ExtractedString('Hello', StringKind::JsonKey, 'blade', new SourceLocation('/a', 1));
        $b = new ExtractedString('Hello', StringKind::JsonKey, 'blade', new SourceLocation('/b', 999));

        expect($a->fingerprint())->toBe($b->fingerprint());
    });

    it('distinguishes JsonKey from ShortKey with the same value', function () {
        $json = new ExtractedString('foo.bar', StringKind::JsonKey, 'blade', new SourceLocation('/x', 1));
        $short = new ExtractedString(
            value: 'foo.bar', kind: StringKind::ShortKey, extractor: 'blade',
            location: new SourceLocation('/x', 1),
            file: 'foo', key: 'bar',
        );

        expect($json->fingerprint())->not->toBe($short->fingerprint());
    });

    it('distinguishes packaged and unpackaged short keys with the same value', function () {
        $plain = new ExtractedString(
            value: 'buttons.submit', kind: StringKind::ShortKey, extractor: 'blade',
            location: new SourceLocation('/x', 1),
            file: 'buttons', key: 'submit',
        );
        $packaged = new ExtractedString(
            value: 'syriable::buttons.submit', kind: StringKind::ShortKey, extractor: 'blade',
            location: new SourceLocation('/x', 1),
            package: 'syriable', file: 'buttons', key: 'submit',
        );

        expect($plain->fingerprint())->not->toBe($packaged->fingerprint());
    });

    it('distinguishes short keys at different directory paths', function () {
        $a = new ExtractedString(
            value: 'profile/buttons.submit', kind: StringKind::ShortKey, extractor: 'blade',
            location: new SourceLocation('/x', 1),
            directories: ['profile'], file: 'buttons', key: 'submit',
        );
        $b = new ExtractedString(
            value: 'admin/buttons.submit', kind: StringKind::ShortKey, extractor: 'blade',
            location: new SourceLocation('/x', 1),
            directories: ['admin'], file: 'buttons', key: 'submit',
        );

        expect($a->fingerprint())->not->toBe($b->fingerprint());
    });
});

describe('ExtractedString — filePath()', function () {
    it('returns null for JSON keys', function () {
        $string = new ExtractedString('Welcome', StringKind::JsonKey, 'blade', new SourceLocation('/x', 1));

        expect($string->filePath())->toBeNull();
    });

    it('returns just file.php for plain short keys', function () {
        $string = new ExtractedString(
            value: 'pagination.next', kind: StringKind::ShortKey, extractor: 'blade',
            location: new SourceLocation('/x', 1),
            file: 'pagination', key: 'next',
        );

        expect($string->filePath())->toBe('pagination.php');
    });

    it('joins directories with the file name', function () {
        $string = new ExtractedString(
            value: 'profile/buttons.submit', kind: StringKind::ShortKey, extractor: 'blade',
            location: new SourceLocation('/x', 1),
            directories: ['profile'], file: 'buttons', key: 'submit',
        );

        expect($string->filePath())->toBe('profile/buttons.php');
    });

    it('handles deep directory nesting', function () {
        $string = new ExtractedString(
            value: 'a/b/c/d.e.f', kind: StringKind::ShortKey, extractor: 'blade',
            location: new SourceLocation('/x', 1),
            directories: ['a', 'b', 'c'], file: 'd', key: 'e.f',
        );

        expect($string->filePath())->toBe('a/b/c/d.php');
    });

    it('does not include the package in the path', function () {
        // The package is a separate field; callers concatenate as needed
        // to build the lang/vendor/{package}/{locale}/{filePath()} layout.
        $string = new ExtractedString(
            value: 'syriable::profile/buttons.submit.label', kind: StringKind::ShortKey, extractor: 'blade',
            location: new SourceLocation('/x', 1),
            package: 'syriable', directories: ['profile'], file: 'buttons', key: 'submit.label',
        );

        expect($string->filePath())->toBe('profile/buttons.php')
            ->and($string->package)->toBe('syriable');
    });
});

describe('ExtractedString — langFilePath()', function () {
    it('returns lang/{locale}.json for JsonKey', function () {
        $string = new ExtractedString('Welcome back', StringKind::JsonKey, 'blade', new SourceLocation('/x', 1));

        expect($string->langFilePath('en'))->toBe('lang/en.json')
            ->and($string->langFilePath('fr'))->toBe('lang/fr.json');
    });

    it('returns lang/{locale}/{file}.php for plain ShortKey', function () {
        $string = new ExtractedString(
            value: 'pagination.next', kind: StringKind::ShortKey, extractor: 'blade',
            location: new SourceLocation('/x', 1),
            file: 'pagination', key: 'next',
        );

        expect($string->langFilePath('en'))->toBe('lang/en/pagination.php');
    });

    it('includes directory chain for nested ShortKeys', function () {
        $string = new ExtractedString(
            value: 'profile/buttons.submit', kind: StringKind::ShortKey, extractor: 'blade',
            location: new SourceLocation('/x', 1),
            directories: ['profile'], file: 'buttons', key: 'submit',
        );

        expect($string->langFilePath('en'))->toBe('lang/en/profile/buttons.php');
    });

    it('handles deeply nested directories', function () {
        $string = new ExtractedString(
            value: 'profile/button/form/icon.submit.label', kind: StringKind::ShortKey, extractor: 'blade',
            location: new SourceLocation('/x', 1),
            directories: ['profile', 'button', 'form'], file: 'icon', key: 'submit.label',
        );

        expect($string->langFilePath('en'))->toBe('lang/en/profile/button/form/icon.php');
    });

    it('puts packaged keys under lang/vendor/{package}/{locale}/', function () {
        $string = new ExtractedString(
            value: 'syriable::buttons.submit', kind: StringKind::ShortKey, extractor: 'blade',
            location: new SourceLocation('/x', 1),
            package: 'syriable', file: 'buttons', key: 'submit',
        );

        expect($string->langFilePath('en'))->toBe('lang/vendor/syriable/en/buttons.php');
    });

    it('combines package and deep directories', function () {
        $string = new ExtractedString(
            value: 'syriable::profile/button/form/icon.submit.label', kind: StringKind::ShortKey, extractor: 'blade',
            location: new SourceLocation('/x', 1),
            package: 'syriable', directories: ['profile', 'button', 'form'], file: 'icon', key: 'submit.label',
        );

        expect($string->langFilePath('en'))
            ->toBe('lang/vendor/syriable/en/profile/button/form/icon.php');
    });

    it('accepts Laravel-style locale identifiers', function () {
        $string = new ExtractedString(
            value: 'pagination.next', kind: StringKind::ShortKey, extractor: 'blade',
            location: new SourceLocation('/x', 1),
            file: 'pagination', key: 'next',
        );

        expect($string->langFilePath('en_US'))->toBe('lang/en_US/pagination.php')
            ->and($string->langFilePath('pt-BR'))->toBe('lang/pt-BR/pagination.php')
            ->and($string->langFilePath('zh_Hant'))->toBe('lang/zh_Hant/pagination.php');
    });

    it('rejects empty locale', function () {
        $string = new ExtractedString('Welcome', StringKind::JsonKey, 'blade', new SourceLocation('/x', 1));

        expect(fn () => $string->langFilePath(''))
            ->toThrow(InvalidArgumentException::class, 'Invalid locale');
    });

    it('rejects locales with path-traversal characters', function () {
        $string = new ExtractedString('Welcome', StringKind::JsonKey, 'blade', new SourceLocation('/x', 1));

        foreach (['en/../etc', 'en/passwd', '..', '/etc', 'en.json', 'en;rm'] as $bad) {
            expect(fn () => $string->langFilePath($bad))
                ->toThrow(InvalidArgumentException::class);
        }
    });
});

describe('ExtractedString — toArray() / fromArray()', function () {
    it('serializes all fields to array', function () {
        $string = new ExtractedString(
            value: 'syriable::profile/buttons.submit.label', kind: StringKind::ShortKey, extractor: 'blade',
            location: new SourceLocation('/app/x.blade.php', 5, 12),
            package: 'syriable', directories: ['profile'], file: 'buttons', key: 'submit.label',
        );

        expect($string->toArray())->toBe([
            'value' => 'syriable::profile/buttons.submit.label',
            'kind' => 'short_key',
            'extractor' => 'blade',
            'location' => ['path' => '/app/x.blade.php', 'line' => 5, 'column' => 12],
            'package' => 'syriable',
            'directories' => ['profile'],
            'file' => 'buttons',
            'key' => 'submit.label',
        ]);
    });

    it('round-trips via toArray and fromArray', function () {
        $original = new ExtractedString(
            value: 'profile/button/form/icon.submit.label', kind: StringKind::ShortKey, extractor: 'blade',
            location: new SourceLocation('/x', 1, 7),
            directories: ['profile', 'button', 'form'], file: 'icon', key: 'submit.label',
        );

        $restored = ExtractedString::fromArray($original->toArray());

        expect($restored->toArray())->toBe($original->toArray());
    });

    it('tolerates missing decomposition fields in input (back-compat)', function () {
        // fromArray() must accept array forms missing the new fields,
        // defaulting them sensibly. JsonKey gets all nulls/empty.
        $data = [
            'value' => 'Welcome',
            'kind' => 'json_key',
            'extractor' => 'blade',
            'location' => ['path' => '/x', 'line' => 1],
        ];

        $string = ExtractedString::fromArray($data);

        expect($string->kind)->toBe(StringKind::JsonKey)
            ->and($string->package)->toBeNull()
            ->and($string->directories)->toBe([])
            ->and($string->file)->toBeNull()
            ->and($string->key)->toBeNull();
    });
});

describe('ExtractedString — withers', function () {
    it('withValue preserves all decomposition fields', function () {
        $original = new ExtractedString(
            value: 'profile/buttons.submit', kind: StringKind::ShortKey, extractor: 'blade',
            location: new SourceLocation('/x', 1),
            directories: ['profile'], file: 'buttons', key: 'submit',
        );

        $modified = $original->withValue('profile/buttons.cancel');

        expect($modified->value)->toBe('profile/buttons.cancel')
            ->and($modified->directories)->toBe(['profile'])
            ->and($modified->file)->toBe('buttons')
            ->and($modified->key)->toBe('submit'); // preserved; caller usually rewrites separately
    });

    it('withValue rejects empty values via the constructor guard', function () {
        $original = new ExtractedString('hello', StringKind::JsonKey, 'blade', new SourceLocation('/x', 1));

        expect(fn () => $original->withValue(''))
            ->toThrow(InvalidArgumentException::class);
    });

    it('withKind allows reclassifying ShortKey → JsonKey with cleared decomposition', function () {
        $original = new ExtractedString(
            value: 'foo.bar', kind: StringKind::ShortKey, extractor: 'blade',
            location: new SourceLocation('/x', 1),
            file: 'foo', key: 'bar',
        );

        $reclassified = $original->withKind(StringKind::JsonKey);

        expect($reclassified->kind)->toBe(StringKind::JsonKey)
            ->and($reclassified->package)->toBeNull()
            ->and($reclassified->directories)->toBe([])
            ->and($reclassified->file)->toBeNull()
            ->and($reclassified->key)->toBeNull();
    });

    it('withKind allows reclassifying JsonKey → ShortKey with explicit decomposition', function () {
        $original = new ExtractedString('foo.bar', StringKind::JsonKey, 'blade', new SourceLocation('/x', 1));

        $reclassified = $original->withKind(StringKind::ShortKey, file: 'foo', key: 'bar');

        expect($reclassified->kind)->toBe(StringKind::ShortKey)
            ->and($reclassified->file)->toBe('foo')
            ->and($reclassified->key)->toBe('bar');
    });
});
