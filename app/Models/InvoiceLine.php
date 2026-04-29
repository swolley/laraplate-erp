<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;
use Override;
use Overtrue\LaravelVersionable\VersionStrategy;

/**
 * Invoice line with optional live FK and immutable fiscal snapshot at posting.
 *
 * @mixin IdeHelperInvoiceLine
 */
class InvoiceLine extends Model
{
    protected VersionStrategy $versionStrategy = VersionStrategy::DIFF;

    protected $fillable = [
        'invoice_id',
        'line_no',
        'description',
        'quantity',
        'unit_price',
        'tax_code_id',
        'tax_code',
        'tax_rate',
        'tax_label',
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

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'line_no' => ['required', 'integer', 'min:1', 'max:65535'],
            'description' => ['nullable', 'string'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'unit_price' => ['required', 'numeric'],
            'tax_code_id' => ['nullable', 'integer', 'exists:tax_codes,id'],
            'tax_code' => ['nullable', 'string', 'max:64'],
            'tax_rate' => ['nullable', 'numeric'],
            'tax_label' => ['nullable', 'string', 'max:255'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'line_no' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'description' => ['nullable', 'string'],
            'quantity' => ['sometimes', 'numeric', 'min:0'],
            'unit_price' => ['sometimes', 'numeric'],
            'tax_code_id' => ['nullable', 'integer', 'exists:tax_codes,id'],
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
        ];
    }
}
