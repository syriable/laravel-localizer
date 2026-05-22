<?php

declare(strict_types=1);

use Syriable\Localizer\Data\ScanResult;
use Syriable\Localizer\Data\StringKind;

describe('ScanResult', function () {
    it('stores all properties', function () {
        $result = new ScanResult(
            strings: [],
            filesScanned: 10,
            filesFromCache: 7,
            durationMs: 12.5,
        );

        expect($result->filesScanned)->toBe(10)
            ->and($result->filesFromCache)->toBe(7)
            ->and($result->durationMs)->toBe(12.5)
            ->and($result->filesFresh())->toBe(3);
    });

    it('rejects negative filesScanned', function () {
        new ScanResult(strings: [], filesScanned: -1, filesFromCache: 0, durationMs: 0.0);
    })->throws(InvalidArgumentException::class, 'filesScanned cannot be negative');

    it('rejects negative filesFromCache', function () {
        new ScanResult(strings: [], filesScanned: 0, filesFromCache: -1, durationMs: 0.0);
    })->throws(InvalidArgumentException::class, 'filesFromCache cannot be negative');

    it('rejects filesFromCache > filesScanned', function () {
        new ScanResult(strings: [], filesScanned: 5, filesFromCache: 6, durationMs: 0.0);
    })->throws(InvalidArgumentException::class, 'cannot exceed filesScanned');

    it('rejects negative durationMs', function () {
        new ScanResult(strings: [], filesScanned: 0, filesFromCache: 0, durationMs: -1.0);
    })->throws(InvalidArgumentException::class, 'durationMs cannot be negative');

    describe('unique()', function () {
        it('deduplicates by fingerprint preserving first-occurrence order', function () {
            $a = makeExtractedString(value: 'one', path: '/a.php');
            $aDup = makeExtractedString(value: 'one', path: '/b.php');
            $b = makeExtractedString(value: 'two', path: '/c.php');

            $result = new ScanResult(
                strings: [$a, $b, $aDup],
                filesScanned: 3,
                filesFromCache: 0,
                durationMs: 0.0,
            );

            $unique = $result->unique();

            expect($unique)->toHaveCount(2)
                ->and($unique[0]->location->path)->toBe('/a.php')
                ->and($unique[1]->value)->toBe('two');
        });

        it('treats ShortKey and JsonKey with same value as distinct', function () {
            $json = makeExtractedString(value: 'auth.failed', kind: StringKind::JsonKey);
            $short = makeExtractedString(
                value: 'auth.failed',
                kind: StringKind::ShortKey,
            );

            $result = new ScanResult(
                strings: [$json, $short],
                filesScanned: 2,
                filesFromCache: 0,
                durationMs: 0.0,
            );

            expect($result->unique())->toHaveCount(2);
        });

        it('returns empty array for empty input', function () {
            $result = new ScanResult([], 0, 0, 0.0);

            expect($result->unique())->toBe([]);
        });
    });

    describe('groupedByKind()', function () {
        it('buckets strings by kind', function () {
            $json = makeExtractedString(value: 'Hello', kind: StringKind::JsonKey);
            $short = makeExtractedString(
                value: 'pagination.next',
                kind: StringKind::ShortKey,
            );

            $result = new ScanResult(
                strings: [$json, $short],
                filesScanned: 2,
                filesFromCache: 0,
                durationMs: 0.0,
            );

            $buckets = $result->groupedByKind();

            expect($buckets)->toHaveKey('json_key')
                ->and($buckets)->toHaveKey('short_key')
                ->and($buckets['json_key'])->toHaveCount(1)
                ->and($buckets['short_key'])->toHaveCount(1);
        });

        it('returns empty buckets when no strings', function () {
            $result = new ScanResult([], 0, 0, 0.0);
            $buckets = $result->groupedByKind();

            expect($buckets['json_key'])->toBe([])
                ->and($buckets['short_key'])->toBe([]);
        });
    });

    describe('groupedByFile()', function () {
        it('buckets short keys by their group prefix', function () {
            $paginationNext = makeExtractedString(
                value: 'pagination.next',
                kind: StringKind::ShortKey,
            );
            $paginationPrev = makeExtractedString(
                value: 'pagination.previous',
                kind: StringKind::ShortKey,
            );
            $authFail = makeExtractedString(
                value: 'auth.failed',
                kind: StringKind::ShortKey,
            );
            $json = makeExtractedString(value: 'Welcome', kind: StringKind::JsonKey);

            $result = new ScanResult(
                strings: [$paginationNext, $authFail, $paginationPrev, $json],
                filesScanned: 4,
                filesFromCache: 0,
                durationMs: 0.0,
            );

            $groups = $result->groupedByFile();

            expect($groups)->toHaveKey('pagination')
                ->and($groups)->toHaveKey('auth')
                ->and($groups)->not->toHaveKey('json_key')
                ->and($groups['pagination'])->toHaveCount(2)
                ->and($groups['auth'])->toHaveCount(1);
        });

        it('merges strings from different directories into the same bucket (known limitation)', function () {
            // groupedByFile() keys on the bare filename, so profile/buttons.submit
            // and admin/buttons.delete both land in 'buttons'.
            $profileButtons = makeExtractedString(value: 'profile/buttons.save', kind: StringKind::ShortKey);
            $adminButtons = makeExtractedString(value: 'admin/buttons.delete', kind: StringKind::ShortKey);

            $result = new ScanResult([$profileButtons, $adminButtons], 2, 0, 0.0);

            $groups = $result->groupedByFile();

            // Both strings collapse into the same 'buttons' bucket.
            expect($groups)->toHaveKey('buttons')
                ->and($groups['buttons'])->toHaveCount(2)
                ->and($groups)->not->toHaveKey('profile/buttons.php')
                ->and($groups)->not->toHaveKey('admin/buttons.php');
        });

        it('excludes JSON keys', function () {
            $json = makeExtractedString(value: 'Hello', kind: StringKind::JsonKey);

            $result = new ScanResult([$json], 1, 0, 0.0);

            expect($result->groupedByFile())->toBe([]);
        });
    });

    describe('groupedByFilePath()', function () {
        it('buckets short keys by their full relative path', function () {
            $paginationNext = makeExtractedString(value: 'pagination.next', kind: StringKind::ShortKey);
            $authFail = makeExtractedString(value: 'auth.failed', kind: StringKind::ShortKey);

            $result = new ScanResult([$paginationNext, $authFail], 2, 0, 0.0);

            $groups = $result->groupedByFilePath();

            expect($groups)->toHaveKey('pagination.php')
                ->and($groups)->toHaveKey('auth.php')
                ->and($groups['pagination.php'])->toHaveCount(1)
                ->and($groups['auth.php'])->toHaveCount(1);
        });

        it('keeps strings from different directories in separate buckets', function () {
            // This is the collision that groupedByFile() cannot avoid.
            $profileButtons = makeExtractedString(value: 'profile/buttons.save', kind: StringKind::ShortKey);
            $adminButtons = makeExtractedString(value: 'admin/buttons.delete', kind: StringKind::ShortKey);

            $result = new ScanResult([$profileButtons, $adminButtons], 2, 0, 0.0);

            $groups = $result->groupedByFilePath();

            expect($groups)->toHaveKey('profile/buttons.php')
                ->and($groups)->toHaveKey('admin/buttons.php')
                ->and($groups['profile/buttons.php'])->toHaveCount(1)
                ->and($groups['admin/buttons.php'])->toHaveCount(1)
                ->and($groups)->not->toHaveKey('buttons');
        });

        it('excludes JSON keys', function () {
            $json = makeExtractedString(value: 'Hello', kind: StringKind::JsonKey);

            $result = new ScanResult([$json], 1, 0, 0.0);

            expect($result->groupedByFilePath())->toBe([]);
        });

        it('returns empty array for an empty result', function () {
            expect((new ScanResult([], 0, 0, 0.0))->groupedByFilePath())->toBe([]);
        });
    });

    it('count() returns total strings including duplicates', function () {
        $a = makeExtractedString(value: 'x');
        $b = makeExtractedString(value: 'x');

        $result = new ScanResult([$a, $b], 2, 0, 0.0);

        expect($result->count())->toBe(2);
    });

    it('serializes to array', function () {
        $string = makeExtractedString(value: 'Hello');
        $result = new ScanResult([$string], 1, 0, 1.5);

        $array = $result->toArray();

        expect($array['strings'])->toHaveCount(1)
            ->and($array['strings'][0])->toHaveKeys(['value', 'kind', 'extractor', 'location', 'package', 'directories', 'file', 'key'])->and($array['filesScanned'])->toBe(1)
            ->and($array['filesFromCache'])->toBe(0)
            ->and($array['durationMs'])->toBe(1.5)
            ->and($array['skippedExtensions'])->toBe([]);
    });

    it('exposes skippedExtensions when provided', function () {
        $result = new ScanResult([], 5, 2, 1.0, ['twig' => 3, 'json' => 7]);

        expect($result->skippedExtensions)->toBe(['twig' => 3, 'json' => 7]);
    });

    it('defaults skippedExtensions to empty for back-compat', function () {
        $result = new ScanResult([], 5, 2, 1.0);

        expect($result->skippedExtensions)->toBe([]);
    });

    it('includes skippedExtensions in toArray', function () {
        $result = new ScanResult([], 0, 0, 0.0, ['twig' => 1]);

        $array = $result->toArray();

        expect($array['skippedExtensions'])->toBe(['twig' => 1]);
    });
});
