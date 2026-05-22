<?php

declare(strict_types=1);

use Syriable\Localizer\Data\DiscoveredFile;
use Syriable\Localizer\Exceptions\LocalizerException;
use Syriable\Localizer\Exceptions\UnknownExtractorException;

describe('LocalizerException', function () {
    it('extends RuntimeException', function () {
        expect(new LocalizerException('x'))->toBeInstanceOf(RuntimeException::class);
    });

    it('wraps a per-file exception with rich context', function () {
        $file = new DiscoveredFile('/app/User.blade.php', 'User.blade.php', 'php', 'blade', 100);
        $cause = new RuntimeException('parse error at line 42');

        $exception = LocalizerException::forFile($file, $cause);

        expect($exception)->toBeInstanceOf(LocalizerException::class)
            ->and($exception->getMessage())->toContain('/app/User.blade.php')
            ->and($exception->getMessage())->toContain('parse error at line 42')
            ->and($exception->getPrevious())->toBe($cause)
            ->and($exception->file())->toBe($file)
            ->and($exception->cacheVersions())->toBeNull();
    });

    it('reports a cache version mismatch with both versions', function () {
        $exception = LocalizerException::cacheVersionMismatch(1, 2);

        expect($exception->getMessage())->toContain('v1')
            ->and($exception->getMessage())->toContain('v2')
            ->and($exception->getMessage())->toContain('--fresh')
            ->and($exception->cacheVersions())->toBe([1, 2])
            ->and($exception->file())->toBeNull();
    });
});

describe('UnknownExtractorException', function () {
    it('extends LocalizerException', function () {
        expect(UnknownExtractorException::forName('x'))->toBeInstanceOf(LocalizerException::class);
    });

    it('includes the extractor name in the message', function () {
        $exception = UnknownExtractorException::forName('mystery');

        expect($exception->getMessage())->toContain('[mystery]');
    });
});
