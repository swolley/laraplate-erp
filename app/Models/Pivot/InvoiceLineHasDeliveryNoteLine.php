<?php

declare(strict_types=1);

namespace Modules\ERP\Models\Pivot;

use Modules\Core\Overrides\Pivot;
use Modules\ERP\Enums\ERPTables;
use Override;

/**
 * Pivot linking invoice lines to delivery-note lines with covered quantity.
 *
 */
final class InvoiceLineHasDeliveryNoteLine extends Pivot
{
    #[Override]
    public $incrementing = true;

    #[Override]
    public $timestamps = true;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::InvoiceLineDeliveryNoteLine->value;

    #[Override]
    protected $fillable = [
        'invoice_line_id',
        'delivery_note_line_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'invoice_line_id' => 'integer',
            'delivery_note_line_id' => 'integer',
            'quantity' => 'decimal:4',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'datetime',
        ];
    }
}
