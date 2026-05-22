<?php

declare(strict_types=1);

namespace Syriable\Localizer\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Syriable\Localizer\Data\ScanResult            scan(?\Syriable\Localizer\Data\ScanRequest $request = null)
 * @method static \Syriable\Localizer\PendingScan                in(string|array<int, string> $paths)
 * @method static \Syriable\Localizer\Localizer                  normalize(\Syriable\Localizer\Contracts\Normalizer|callable $normalizer)
 * @method static \Syriable\Localizer\Localizer                  withoutNormalizers()
 * @method static list<\Syriable\Localizer\Contracts\Normalizer> normalizers()
 * @method static \Syriable\Localizer\Support\ExtractorRegistry  extractors()
 *
 * @see \Syriable\Localizer\Localizer
 */
final class Localizer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Syriable\Localizer\Localizer::class;
    }
}
