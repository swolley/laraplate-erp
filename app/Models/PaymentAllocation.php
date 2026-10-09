<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Contracts\IsPartOfParent;
use Modules\Core\Overrides\Model;
use Modules\ERP\Enums\ERPTables;
use Override;

final class PaymentAllocation extends Model implements IsPartOfParent
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
     * The relation to the record this one only exists inside, whose visibility it inherits.
     */
    #[Override]
    public function parentRelation(): string
    {
        return 'payment';
    }

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
