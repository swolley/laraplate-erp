<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Core\Overrides\Model;
use Modules\ERP\Casts\PaymentScheduleStatus;
use Modules\ERP\Concerns\BelongsToCompany;
use Override;

/**
 * @mixin IdeHelperPaymentScheduleLine
 */
class PaymentScheduleLine extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'invoice_id',
        'due_date',
        'amount_doc',
        'currency_doc',
        'amount_local',
        'currency_local',
        'fx_rate',
        'paid_amount_doc',
        'paid_amount_local',
        'status',
        'paid_at',
    ];

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsToMany<Payment, $this>
     */
    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'payment_allocations')
            ->withPivot(['allocated_amount_doc', 'allocated_amount_local'])
            ->withTimestamps();
    }

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'due_date' => ['required', 'date'],
            'amount_doc' => ['required', 'numeric', 'min:0.0001'],
            'currency_doc' => ['required', 'string', 'size:3'],
            'amount_local' => ['required', 'numeric', 'min:0.0001'],
            'currency_local' => ['required', 'string', 'size:3'],
            'fx_rate' => ['required', 'numeric', 'min:0'],
            'paid_amount_doc' => ['numeric', 'min:0'],
            'paid_amount_local' => ['numeric', 'min:0'],
            'status' => ['string', PaymentScheduleStatus::validationRule()],
            'paid_at' => ['nullable', 'date'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'invoice_id' => ['sometimes', 'integer', 'exists:invoices,id'],
            'due_date' => ['sometimes', 'date'],
            'amount_doc' => ['sometimes', 'numeric', 'min:0.0001'],
            'currency_doc' => ['sometimes', 'string', 'size:3'],
            'amount_local' => ['sometimes', 'numeric', 'min:0.0001'],
            'currency_local' => ['sometimes', 'string', 'size:3'],
            'fx_rate' => ['sometimes', 'numeric', 'min:0'],
            'paid_amount_doc' => ['numeric', 'min:0'],
            'paid_amount_local' => ['numeric', 'min:0'],
            'status' => ['string', PaymentScheduleStatus::validationRule()],
            'paid_at' => ['nullable', 'date'],
        ]);

        return $rules;
    }

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'amount_doc' => 'decimal:4',
            'amount_local' => 'decimal:4',
            'fx_rate' => 'decimal:8',
            'paid_amount_doc' => 'decimal:4',
            'paid_amount_local' => 'decimal:4',
            'status' => PaymentScheduleStatus::class,
            'paid_at' => 'immutable_datetime',
        ];
    }
}
