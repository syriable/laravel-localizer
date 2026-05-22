<?php

declare(strict_types=1);

use Syriable\Localizer\Data\DiscoveredFile;

describe('DiscoveredFile', function () {
    it('stores all properties', function () {
        $file = new DiscoveredFile(
            absolutePath: '/abs/welcome.blade.php',
            relativePath: 'welcome.blade.php',
            extension: 'php',
            extractor: 'blade',
            size: 1024,
        );

        expect($file->absolutePath)->toBe('/abs/welcome.blade.php')
            ->and($file->relativePath)->toBe('welcome.blade.php')
            ->and($file->extension)->toBe('php')
            ->and($file->extractor)->toBe('blade')
            ->and($file->size)->toBe(1024);
    });

    it('rejects empty absolute path', function () {
        new DiscoveredFile('', 'x', 'php', 'blade', 0);
    })->throws(InvalidArgumentException::class, 'absolutePath cannot be empty');

    it('rejects empty extractor', function () {
        new DiscoveredFile('/a', 'a', 'php', '', 0);
    })->throws(InvalidArgumentException::class, 'extractor name cannot be empty');

    it('rejects negative size', function () {
        new DiscoveredFile('/a', 'a', 'php', 'blade', -1);
    })->throws(InvalidArgumentException::class, 'size cannot be negative');

    it('allows zero-byte files', function () {
        $file = new DiscoveredFile('/a', 'a', 'php', 'blade', 0);

        expect($file->size)->toBe(0);
    });
});
