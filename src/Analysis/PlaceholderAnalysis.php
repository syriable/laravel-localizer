<?php

declare(strict_types=1);

namespace Syriable\Localizer\Analysis;

/**
 * The analysis of a single placeholder in a translation call.
 *
 * For an input like:
 *
 *     __('hello', ['user' => $user->profile->name])
 *
 * one PlaceholderAnalysis is produced:
 *
 *     placeholder = ":user"
 *     source      = "$user->profile->name"
 *     type        = PlaceholderType::NestedObjectProperty
 *     structure   = ["object" => "$user", "path" => ["profile", "name"]]
 *
 * The `$source` field always preserves the verbatim PHP expression so that
 * no information from the call site is lost — downstream tools can render
 * the original code back into prompts or reports.
 */
final readonly class PlaceholderAnalysis
{
    /**
     * @param array<string, mixed> $structure Type-specific structural details. See
     *                                        {@see PhpExpressionClassifier} for the
     *                                        shape produced for each {@see PlaceholderType}.
     */
    public function __construct(
        public string $placeholder,
        public string $source,
        public PlaceholderType $type,
        public array $structure,
    ) {}

    /**
     * @return array{
     *     placeholder: string,
     *     source: string,
     *     type: string,
     *     structure: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'placeholder' => $this->placeholder,
            'source' => $this->source,
            'type' => $this->type->value,
            'structure' => $this->structure,
        ];
    }
}
