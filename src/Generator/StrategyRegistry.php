<?php

declare(strict_types=1);

namespace Syriable\Localizer\Generator;

use Syriable\Localizer\Contracts\GenerationStrategy;
use Syriable\Localizer\Exceptions\LocalizerException;

/**
 * Registry for translation value generation strategies.
 *
 * Strategies are registered by name. The generator command resolves the
 * user-supplied `--strategy` option through this registry. Custom strategies
 * can be registered from a service provider.
 */
final class StrategyRegistry
{
    /** @var array<string, GenerationStrategy> */
    private array $strategies = [];

    public function register(GenerationStrategy $strategy): void
    {
        $this->strategies[$strategy->name()] = $strategy;
    }

    public function get(string $name): GenerationStrategy
    {
        if (! isset($this->strategies[$name])) {
            $available = implode(', ', array_keys($this->strategies));

            throw LocalizerException::unknownStrategy($name, $available);
        }

        return $this->strategies[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->strategies[$name]);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->strategies);
    }
}
