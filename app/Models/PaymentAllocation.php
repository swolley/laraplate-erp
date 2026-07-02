<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;
use Modules\ERP\Enums\ERPTables;
use Override;

/**
 * @property int|string $id
 * @property int $payment_id
 * @property int $payment_schedule_line_id
 * @property numeric-string $allocated_amount_doc
 * @property numeric-string $allocated_amount_local
 * @mixin \Eloquent
 * @mixin IdeHelperPaymentAllocation
 */
final class PaymentAllocation extends Model
{
    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::PaymentAllocations->value;

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
    protected $fillable = [
        'payment_id',
        'payment_schedule_line_id',
        'allocated_amount_doc',
        'allocated_amount_local',
    ];

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<PaymentScheduleLine, $this>
     */
    public function schedule_line(): BelongsTo
    {
        return $this->belongsTo(PaymentScheduleLine::class, 'payment_schedule_line_id');
    }

    /**
     * @return array<string, mixed>
     */

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'payment_id' => ['required', 'integer', 'exists:' . ERPTables::Payments->value . ',id'],
            'payment_schedule_line_id' => ['required', 'integer', 'exists:' . ERPTables::PaymentScheduleLines->value . ',id'],
            'allocated_amount_doc' => ['required', 'numeric', 'min:0.0001'],
            'allocated_amount_local' => ['required', 'numeric', 'min:0.0001'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'payment_id' => ['sometimes', 'integer', 'exists:' . ERPTables::Payments->value . ',id'],
            'payment_schedule_line_id' => ['sometimes', 'integer', 'exists:' . ERPTables::PaymentScheduleLines->value . ',id'],
            'allocated_amount_doc' => ['sometimes', 'numeric', 'min:0.0001'],
            'allocated_amount_local' => ['sometimes', 'numeric', 'min:0.0001'],
        ]);

        return $rules;
    }

    protected function casts(): array
    {
        return [
            'allocated_amount_doc' => 'decimal:4',
            'allocated_amount_local' => 'decimal:4',
        ];
    }
}
