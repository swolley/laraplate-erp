<?php

declare(strict_types=1);

namespace Modules\ERP\Casts;

enum EInvoiceSubmissionStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Submitted = 'submitted';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Error = 'error';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function validationRule(): string
    {
        return 'in:' . implode(',', self::values());
    }
}
