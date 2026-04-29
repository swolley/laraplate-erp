<?php

declare(strict_types=1);

namespace Modules\ERP\Exceptions;

use RuntimeException;

final class TaxCodeNotActiveException extends RuntimeException
{
    public static function forCode(string $code, int $company_id): self
    {
        return new self(
            'No active tax code "' . $code . '" for company ' . $company_id . ' at the requested date.',
        );
    }
}
