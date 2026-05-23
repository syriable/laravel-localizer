<?php

declare(strict_types=1);

namespace Syriable\Localizer\Generator\Strategies;

use Syriable\Localizer\AI\AiTranslationCache;
use Syriable\Localizer\AI\AiTranslationClient;
use Syriable\Localizer\AI\PlaceholderMasker;
use Syriable\Localizer\Analysis\PlaceholderAnalysis;
use Syriable\Localizer\Analysis\PlaceholderType;
use Syriable\Localizer\Analysis\TranslationCallAnalysis;
use Syriable\Localizer\Contracts\AnalysisAwareStrategy;
use Syriable\Localizer\Contracts\GenerationStrategy;
use Syriable\Localizer\Contracts\LocaleAwareStrategy;

/**
 * Translation value generation strategy powered by the Anthropic API.
 *
 * For each missing key the strategy:
 *   1. Masks any `:placeholder` tokens so the model cannot translate them.
 *   2. Checks the persistent AI translation cache.
 *   3. Calls the Anthropic Messages API on a cache miss, optionally enriching
 *      the prompt with placeholder context derived from call-site analysis.
 *   4. Stores the result in the cache and restores the original placeholders.
 *   5. Falls back to the configured fallback strategy on any API error.
 *
 * The strategy implements {@see LocaleAwareStrategy} so the pipeline can
 * supply the target locale before generation begins, and
 * {@see AnalysisAwareStrategy} so the pipeline can inject call-site analysis
 * that enriches the translation prompt with placeholder semantics.
 */
final class AiGenerationStrategy implements AnalysisAwareStrategy, LocaleAwareStrategy
{
    /**
     * @param array<string, TranslationCallAnalysis> $analyses Call-site analyses keyed by key.
     */
    public function __construct(
        private readonly AiTranslationClient $client,
        private readonly AiTranslationCache $cache,
        private readonly PlaceholderMasker $masker,
        private readonly GenerationStrategy $fallback,
        private readonly string $sourceLocale,
        private readonly string $targetLocale = 'en',
        private readonly array $analyses = [],
    ) {}

    public function name(): string
    {
        return 'ai';
    }

    public function generate(string $key): string
    {
        if ($this->targetLocale === $this->sourceLocale) {
            return $this->fallback->generate($key);
        }

        ['masked' => $masked, 'map' => $map] = $this->masker->mask($key);

        $contextBlock = $this->buildPlaceholderContext($key, $map);

        $cacheKey = $this->cache->makeKey(
            $this->client->model(),
            $this->sourceLocale,
            $this->targetLocale,
            $masked.$contextBlock,
        );

        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return $this->masker->unmask($cached, $map);
        }

        try {
            $translated = $this->client->translate($masked, $this->sourceLocale, $this->targetLocale, $contextBlock);
            $this->cache->set($cacheKey, $translated);

            return $this->masker->unmask($translated, $map);
        } catch (\Throwable) {
            return $this->fallback->generate($key);
        }
    }

    public function withLocale(string $locale): static
    {
        return new self(
            client: $this->client,
            cache: $this->cache,
            masker: $this->masker,
            fallback: $this->fallback,
            sourceLocale: $this->sourceLocale,
            targetLocale: $locale,
            analyses: $this->analyses,
        );
    }

    public function withAnalysis(array $analyses): static
    {
        return new self(
            client: $this->client,
            cache: $this->cache,
            masker: $this->masker,
            fallback: $this->fallback,
            sourceLocale: $this->sourceLocale,
            targetLocale: $this->targetLocale,
            analyses: $analyses,
        );
    }

    /**
     * Builds a context block describing masked placeholder tokens for the AI.
     *
     * When call-site analysis is available for the key, each `{{Pn}}` token
     * is described with its original `:placeholder` name and the classified
     * expression type, helping the model understand the semantic role of each
     * placeholder in the translated phrase.
     *
     * @param array<string, string> $maskMap Token → original placeholder (e.g. `"{{P0}}" => ":name"`).
     */
    private function buildPlaceholderContext(string $key, array $maskMap): string
    {
        if ($maskMap === []) {
            return '';
        }

        $analysis = $this->analyses[$key] ?? null;

        $lines = [];

        foreach ($maskMap as $token => $original) {
            if ($analysis !== null) {
                $placeholderInfo = $this->findPlaceholder($analysis, $original);

                if ($placeholderInfo !== null) {
                    $typeDesc = $this->describeType($placeholderInfo->type);
                    $lines[] = "  - {$token} represents '{$original}' ({$typeDesc})";

                    continue;
                }
            }

            $lines[] = "  - {$token} represents '{$original}'";
        }

        return "\nPlaceholder context (preserve these tokens exactly):\n".implode("\n", $lines)."\n";
    }

    private function findPlaceholder(TranslationCallAnalysis $analysis, string $placeholderName): ?PlaceholderAnalysis
    {
        foreach ($analysis->placeholders as $p) {
            if ($p->placeholder === $placeholderName) {
                return $p;
            }
        }

        return null;
    }

    private function describeType(PlaceholderType $type): string
    {
        return match ($type) {
            PlaceholderType::Variable => 'a PHP variable',
            PlaceholderType::ObjectProperty => 'an object property',
            PlaceholderType::NestedObjectProperty => 'a nested object property',
            PlaceholderType::FunctionCall => 'a function return value',
            PlaceholderType::MethodCall => 'a method return value',
            PlaceholderType::StaticMethodCall => 'a static method return value',
            PlaceholderType::Literal => 'a literal value',
            PlaceholderType::Expression => 'a computed expression',
        };
    }
}
