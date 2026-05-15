<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Core\Overrides\Model;
use Modules\ERP\Casts\MatchStatus;
use Modules\ERP\Enums\ERPTables;
use Override;
use Overtrue\LaravelVersionable\VersionStrategy;

/**
 * Invoice line with optional live FK and immutable fiscal snapshot at posting.
 *
 * @mixin \Eloquent
 * @mixin IdeHelperInvoiceLine
 */
final class InvoiceLine extends Model
{
    #[Override]
    protected $table = ERPTables::InvoiceLines->value;

    /**
     * The attributes that are mass assignable.
     */
    #[\Override]
    protected $fillable = [
        'invoice_id',
        'line_no',
        'description',
        'quantity',
        'unit_price',
        'tax_code_id',
        'sales_order_line_id',
        'tax_code',
        'tax_rate',
        'tax_label',
        'purchase_order_line_id',
        'goods_receipt_line_id',
        'match_status',
        'match_discrepancy',
    ];

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Optional link to the {@see TaxCode} row used when the line was built (snapshot columns remain authoritative).
     */
    public function applied_tax_code(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class, 'tax_code_id');
    }

    /**
     * @return BelongsTo<SalesOrderLine, $this>
     */
    public function sales_order_line(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class);
    }

    /**
     * @return BelongsTo<PurchaseOrderLine, $this>
     */
    public function purchase_order_line(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    /**
     * @return BelongsTo<GoodsReceiptLine, $this>
     */
    public function goods_receipt_line(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptLine::class);
    }

    /**
     * @return BelongsToMany<DeliveryNoteLine, $this>
     */
    public function delivery_note_lines(): BelongsToMany
    {
        return $this->belongsToMany(
            DeliveryNoteLine::class,
            'invoice_line_delivery_note_line',
        )->withPivot('quantity');
    }

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'invoice_id' => ['required', 'integer', 'exists:'.ERPTables::Invoices->value.',id'],
            'line_no' => ['required', 'integer', 'min:1', 'max:65535'],
            'description' => ['nullable', 'string'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'unit_price' => ['required', 'numeric'],
            'tax_code_id' => ['nullable', 'integer', 'exists:'.ERPTables::TaxCodes->value.',id'],
            'sales_order_line_id' => ['nullable', 'integer', 'exists:'.ERPTables::SalesOrderLines->value.',id'],
            'tax_code' => ['nullable', 'string', 'max:64'],
            'tax_rate' => ['nullable', 'numeric'],
            'tax_label' => ['nullable', 'string', 'max:255'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'line_no' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'description' => ['nullable', 'string'],
            'quantity' => ['sometimes', 'numeric', 'min:0'],
            'unit_price' => ['sometimes', 'numeric'],
            'tax_code_id' => ['nullable', 'integer', 'exists:'.ERPTables::TaxCodes->value.',id'],
            'sales_order_line_id' => ['nullable', 'integer', 'exists:'.ERPTables::SalesOrderLines->value.',id'],
            'tax_code' => ['nullable', 'string', 'max:64'],
            'tax_rate' => ['nullable', 'numeric'],
            'tax_label' => ['nullable', 'string', 'max:255'],
        ]);

        return $rules;
    }

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'tax_rate' => 'decimal:4',
            'match_status' => MatchStatus::class,
            'match_discrepancy' => 'array',
        ];
    }

    protected function shouldVersioning(): bool
    {
        return false;
    }
}
