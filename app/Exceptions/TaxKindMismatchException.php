<?php

declare(strict_types=1);

namespace Modules\ERP\Exceptions;

use RuntimeException;

final class TaxKindMismatchException extends RuntimeException
{
    public static function expected(string $expected, string $actual): self
    {
        return new self('Expected tax kind ' . $expected . ', got ' . $actual . '.');
    }
}
