<?php

declare(strict_types=1);

namespace Modules\Business\Casts;

/**
 * Document kinds that consume a per-company fiscal or operational number sequence.
 */
enum DocumentType: string
{
    case Quotation = 'quotation';
    case SalesInvoice = 'sales_invoice';
    case PurchaseInvoice = 'purchase_invoice';
    case CreditNote = 'credit_note';
    case InternalJournal = 'internal_journal';

    /**
     * @return array<string>
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
