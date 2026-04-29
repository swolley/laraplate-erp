<?php

declare(strict_types=1);

namespace Modules\ERP\Exceptions;

use RuntimeException;

final class TaxCodeImmutableAttributeException extends RuntimeException
{
    public static function make(string $attribute): self
    {
        return new self('Tax code attribute "' . $attribute . '" is immutable after create.');
    }
}
