<?php

declare(strict_types=1);

use Syriable\Localizer\Data\SourceLocation;

describe('SourceLocation', function () {
    it('stores path, line, and column', function () {
        $location = new SourceLocation('/abs/path.php', line: 42, column: 7);

        expect($location->path)->toBe('/abs/path.php')
            ->and($location->line)->toBe(42)
            ->and($location->column)->toBe(7);
    });

    it('defaults column to zero', function () {
        $location = new SourceLocation('/abs/path.php', line: 1);

        expect($location->column)->toBe(0);
    });

    it('rejects empty path', function () {
        new SourceLocation('', line: 1);
    })->throws(InvalidArgumentException::class, 'path cannot be empty');

    it('rejects zero or negative line', function () {
        new SourceLocation('/abs/path.php', line: 0);
    })->throws(InvalidArgumentException::class, 'line must be >= 1');

    it('rejects negative column', function () {
        new SourceLocation('/abs/path.php', line: 1, column: -1);
    })->throws(InvalidArgumentException::class, 'column must be >= 0');

    it('renders to a path:line:column string', function () {
        $location = new SourceLocation('/abs/file.php', line: 10, column: 3);

        expect($location->toString())->toBe('/abs/file.php:10:3');
    });

    it('serializes to array', function () {
        $location = new SourceLocation('/abs/file.php', line: 10, column: 3);

        expect($location->toArray())->toBe([
            'path' => '/abs/file.php',
            'line' => 10,
            'column' => 3,
        ]);
    });

    it('is immutable', function () {
        $location = new SourceLocation('/abs/file.php', line: 1);
        $ref = new ReflectionClass($location);

        expect($ref->isReadOnly())->toBeTrue();
    });
});
