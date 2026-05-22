<?php

declare(strict_types=1);

namespace Syriable\Localizer\Data;

/**
 * Pinpoints where an extracted string originated in source code.
 *
 * The `path` is always the absolute filesystem path. `line` is 1-indexed.
 * `column` is 0-indexed and best-effort — extractors that cannot determine
 * a meaningful column fall back to 0.
 */
final readonly class SourceLocation
{
    public function __construct(
        public string $path,
        public int $line,
        public int $column = 0,
    ) {
        if ($path === '') {
            throw new \InvalidArgumentException('SourceLocation path cannot be empty.');
        }

        if ($line < 1) {
            throw new \InvalidArgumentException('SourceLocation line must be >= 1.');
        }

        if ($column < 0) {
            throw new \InvalidArgumentException('SourceLocation column must be >= 0.');
        }
    }

    /**
     * Human-readable rendering, suitable for log lines and CLI output.
     */
    public function toString(): string
    {
        return "{$this->path}:{$this->line}:{$this->column}";
    }

    /**
     * @return array{path: string, line: int, column: int}
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'line' => $this->line,
            'column' => $this->column,
        ];
    }
}
