<?php

declare(strict_types=1);

use Syriable\Localizer\Data\StringKind;

describe('StringKind', function () {
    it('has two cases', function () {
        expect(StringKind::cases())->toHaveCount(2);
    });

    it('uses snake_case string values', function () {
        expect(StringKind::ShortKey->value)->toBe('short_key')
            ->and(StringKind::JsonKey->value)->toBe('json_key');
    });

    it('is constructible from a string', function () {
        expect(StringKind::from('short_key'))->toBe(StringKind::ShortKey)
            ->and(StringKind::from('json_key'))->toBe(StringKind::JsonKey);
    });

    it('throws on unknown value', function () {
        StringKind::from('unknown');
    })->throws(ValueError::class);
});
