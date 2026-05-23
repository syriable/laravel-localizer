<?php

declare(strict_types=1);

use Syriable\Localizer\Data\GenerationResult;

describe('GenerationResult', function () {
    it('starts with empty collections and zero counts', function () {
        $result = new GenerationResult(locale: 'en', dryRun: false);

        expect($result->written)->toBe([])
            ->and($result->skipped)->toBe([])
            ->and($result->preview)->toBe([])
            ->and($result->keysAdded)->toBe(0);
    });

    it('calculates filesWritten from the written list', function () {
        $result = new GenerationResult(locale: 'en', dryRun: false);
        $result->written = ['/a.php', '/b.php'];

        expect($result->filesWritten())->toBe(2);
    });

    it('calculates filesSkipped from the skipped list', function () {
        $result = new GenerationResult(locale: 'en', dryRun: false);
        $result->skipped = ['/c.php'];

        expect($result->filesSkipped())->toBe(1);
    });

    it('calculates totalFiles as sum of written and skipped', function () {
        $result = new GenerationResult(locale: 'en', dryRun: false);
        $result->written = ['/a.php', '/b.php'];
        $result->skipped = ['/c.php'];

        expect($result->totalFiles())->toBe(3);
    });

    it('serialises to array correctly', function () {
        $result = new GenerationResult(locale: 'fr', dryRun: true);
        $result->written = ['/a.php'];
        $result->keysAdded = 5;

        $array = $result->toArray();

        expect($array['locale'])->toBe('fr')
            ->and($array['dryRun'])->toBeTrue()
            ->and($array['filesWritten'])->toBe(1)
            ->and($array['filesSkipped'])->toBe(0)
            ->and($array['keysAdded'])->toBe(5)
            ->and($array['written'])->toBe(['/a.php']);
    });
});
