<?php

declare(strict_types=1);

namespace Syriable\Localizer\Pipeline;

use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Pipeline\Pipeline;
use Syriable\Localizer\Data\ScanRequest;
use Syriable\Localizer\Data\ScanResult;
use Syriable\Localizer\Events\ScanCompleted;
use Syriable\Localizer\Events\ScanStarted;

/**
 * Runs the full extraction pipeline.
 *
 * Wraps Laravel's standard {@see Pipeline} primitive — the same one used
 * for middleware. Each stage is resolved from the container, allowing
 * tests and downstream packages to swap individual stages without
 * subclassing the pipeline.
 *
 * The whole pipeline runs under a Laravel cache lock so concurrent
 * processes (parallel CI jobs sharing a cache file) serialise rather
 * than corrupt each other.
 */
final class ScanPipeline
{
    /**
     * @param list<class-string> $stages
     */
    public function __construct(
        private readonly Pipeline $pipeline,
        private readonly Dispatcher $events,
        private readonly CacheFactory $cacheFactory,
        private readonly array $stages,
        private readonly string $lockName,
        private readonly int $lockSeconds,
    ) {}

    public function run(ScanRequest $request): ScanResult
    {
        $this->events->dispatch(new ScanStarted($request));

        $start = hrtime(true);

        $result = $this->withOptionalLock(fn (): ScanResult => $this->runStages($request, $start));

        $this->events->dispatch(new ScanCompleted($result));

        return $result;
    }

    /**
     * @template T
     *
     * @param  \Closure(): T $callback
     * @return T
     */
    private function withOptionalLock(\Closure $callback): mixed
    {
        $store = $this->resolveLockStore();

        if ($store === null) {
            return $callback();
        }

        $lock = $store->lock($this->lockName, $this->lockSeconds);

        try {
            $lock->block($this->lockSeconds);

            return $callback();
        } finally {
            $lock->release();
        }
    }

    private function resolveLockStore(): ?LockProvider
    {
        try {
            $repository = $this->cacheFactory->store();
        } catch (\Throwable) {
            return null;
        }

        $underlying = $repository instanceof Repository
            ? $repository->getStore()
            : null;

        return $underlying instanceof LockProvider ? $underlying : null;
    }

    private function runStages(ScanRequest $request, float|int $start): ScanResult
    {
        /** @var ScanPayload $finalPayload */
        $finalPayload = $this->pipeline
            ->send(new ScanPayload($request))
            ->through($this->stages)
            ->thenReturn();

        $durationMs = (hrtime(true) - $start) / 1_000_000.0;

        return $finalPayload->toResult($durationMs);
    }
}
