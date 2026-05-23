<?php

declare(strict_types=1);

use Syriable\Localizer\Contracts\GenerationStrategy;
use Syriable\Localizer\Exceptions\LocalizerException;
use Syriable\Localizer\Generator\Strategies\EmptyStrategy;
use Syriable\Localizer\Generator\Strategies\HumanizedStrategy;
use Syriable\Localizer\Generator\Strategies\KeyStrategy;
use Syriable\Localizer\Generator\StrategyRegistry;

describe('StrategyRegistry', function () {
    beforeEach(function () {
        $this->registry = new StrategyRegistry;
    });

    it('returns a registered strategy by name', function () {
        $this->registry->register(new HumanizedStrategy);

        expect($this->registry->get('humanized'))->toBeInstanceOf(HumanizedStrategy::class);
    });

    it('throws on unknown strategy', function () {
        expect(fn () => $this->registry->get('unknown'))
            ->toThrow(LocalizerException::class);
    });

    it('registers and retrieves all three built-in strategies', function () {
        $this->registry->register(new HumanizedStrategy);
        $this->registry->register(new KeyStrategy);
        $this->registry->register(new EmptyStrategy);

        expect($this->registry->get('humanized'))->toBeInstanceOf(HumanizedStrategy::class)
            ->and($this->registry->get('key'))->toBeInstanceOf(KeyStrategy::class)
            ->and($this->registry->get('empty'))->toBeInstanceOf(EmptyStrategy::class);
    });

    it('reports whether a strategy is registered', function () {
        $this->registry->register(new HumanizedStrategy);

        expect($this->registry->has('humanized'))->toBeTrue()
            ->and($this->registry->has('nonexistent'))->toBeFalse();
    });

    it('lists all registered strategy names', function () {
        $this->registry->register(new HumanizedStrategy);
        $this->registry->register(new KeyStrategy);

        expect($this->registry->names())->toBe(['humanized', 'key']);
    });

    it('overwrites a strategy when the same name is registered twice', function () {
        $this->registry->register(new HumanizedStrategy);

        $custom = new class implements GenerationStrategy
        {
            public function name(): string
            {
                return 'humanized';
            }

            public function generate(string $key): string
            {
                return 'CUSTOM';
            }
        };
        $this->registry->register($custom);

        expect($this->registry->get('humanized')->generate('any'))->toBe('CUSTOM');
    });
});
