<?php

declare(strict_types=1);

use Syriable\Localizer\Cache\FileScanCache;
use Syriable\Localizer\Cache\NullScanCache;
use Syriable\Localizer\Contracts\ScanCache;
use Syriable\Localizer\Localizer;
use Syriable\Localizer\Pipeline\ScanPipeline;
use Syriable\Localizer\Support\ExtractorRegistry;

it('binds Localizer as a singleton', function () {
    expect($this->app->make(Localizer::class))->toBe($this->app->make(Localizer::class));
});

it('binds ScanPipeline as a singleton', function () {
    expect($this->app->make(ScanPipeline::class))->toBe($this->app->make(ScanPipeline::class));
});

it('binds ScanCache as FileScanCache when enabled', function () {
    expect($this->app->make(ScanCache::class))->toBeInstanceOf(FileScanCache::class);
});

it('binds ScanCache as NullScanCache when disabled', function () {
    config()->set('localizer.cache.enabled', false);
    $this->app->forgetInstance(ScanCache::class);

    expect($this->app->make(ScanCache::class))->toBeInstanceOf(NullScanCache::class);
});

it('registers all configured extractors in the registry', function () {
    $registry = $this->app->make(ExtractorRegistry::class);

    expect($registry->has('blade'))->toBeTrue()
        ->and($registry->has('php'))->toBeTrue()
        ->and($registry->has('vue'))->toBeTrue()
        ->and($registry->has('javascript'))->toBeTrue()
        ->and($registry->has('typescript'))->toBeTrue()
        ->and($registry->has('livewire'))->toBeTrue()
        ->and($registry->has('inertia'))->toBeTrue();
});

it('preserves the registration order from config', function () {
    $registry = $this->app->make(ExtractorRegistry::class);

    $names = array_keys($registry->all());

    $bladeIdx = array_search('blade', $names, true);
    $phpIdx = array_search('php', $names, true);
    $livewireIdx = array_search('livewire', $names, true);

    expect($bladeIdx)->toBeLessThan($phpIdx);
    expect($livewireIdx)->toBeLessThan($phpIdx);
});

it('publishes the config file', function () {
    $this->artisan('vendor:publish', ['--tag' => 'localizer-config'])
        ->assertExitCode(0);
});

it('resolves the facade to the engine', function () {
    expect(Syriable\Localizer\Facades\Localizer::getFacadeRoot())
        ->toBeInstanceOf(Localizer::class);
});
