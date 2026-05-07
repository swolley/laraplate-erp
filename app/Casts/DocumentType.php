<?php

declare(strict_types=1);

namespace Modules\ERP\Casts;

/**
 * Document kinds that consume a per-company fiscal or operational number sequence.
 */
enum DocumentType: string
{
    case Quotation = 'quotation';
    case SalesOrder = 'sales_order';
    case PurchaseOrder = 'purchase_order';
    case SalesInvoice = 'sales_invoice';
    case PurchaseInvoice = 'purchase_invoice';
    case SalesCreditNote = 'sales_credit_note';
    case PurchaseCreditNote = 'purchase_credit_note';
    case SalesDebitNote = 'sales_debit_note';
    case PurchaseDebitNote = 'purchase_debit_note';
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

    /**
     * Whether a rollback of the surrounding transaction may leave numbering holes.
     *
     * Fiscal / legal streams keep false so operators treat each allocated number as consumed.
     * Operational order streams (quotation, sales order, purchase order) allow gaps on rollbacks.
     */
    public function defaultGapAllowed(): bool
    {
        return match ($this) {
            self::Quotation, self::SalesOrder, self::PurchaseOrder => true,
            default => false,
        };
    }
}
