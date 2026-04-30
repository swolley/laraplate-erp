<?php

declare(strict_types=1);

namespace Modules\ERP\Casts;

enum EInvoiceSubmissionStatus: string
{
    case DRAFT = 'draft';
    case QUEUED = 'queued';
    case SUBMITTED = 'submitted';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case ERROR = 'error';

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
