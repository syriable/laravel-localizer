<?php

declare(strict_types=1);

namespace Syriable\Localizer\Exceptions;

final class UnknownExtractorException extends LocalizerException
{
    public static function forName(string $name): self
    {
        return new self("No extractor is registered under the name [{$name}].");
    }
}
