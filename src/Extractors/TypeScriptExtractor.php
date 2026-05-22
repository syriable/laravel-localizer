<?php

declare(strict_types=1);

namespace Syriable\Localizer\Extractors;

/**
 * Extracts translatable strings from TypeScript source files.
 *
 * Identical extraction logic to {@see JavaScriptExtractor}; differs only
 * in the file patterns it matches. Kept as a subclass so users can
 * customise TS independently of JS (e.g. add `.d.ts` filtering).
 */
final class TypeScriptExtractor extends JavaScriptExtractor
{
    public function name(): string
    {
        return 'typescript';
    }

    public function patterns(): array
    {
        return ['*.ts', '*.tsx', '*.mts', '*.cts'];
    }
}
