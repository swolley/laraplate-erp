<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\User;
use Modules\Core\Overrides\Model;
use Modules\ERP\Enums\ERPTables;
use Override;

/**
 * @mixin \Eloquent
 * @mixin IdeHelperPoolTransaction
 */
final class PoolTransaction extends Model
{
    #[Override]
    protected $table = ERPTables::PoolTransactions->value;

    #[Override]
    protected $fillable = ['partner_pool_id', 'from_user_id', 'to_user_id', 'amount', 'currency', 'occurred_on', 'confirmed_at', 'description'];

    public function partner_pool(): BelongsTo
    {
        return $this->belongsTo(PartnerPool::class);
    }

    public function from_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function to_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $attributes = [
            'partner_pool_id' => ['required', 'integer', 'exists:' . ERPTables::PartnerPools->value . ',id'],
            'from_user_id' => ['required', 'integer', 'different:to_user_id'],
            'to_user_id' => ['required', 'integer', 'different:from_user_id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', 'string', 'size:3'],
            'occurred_on' => ['required', 'date'],
            'confirmed_at' => ['required', 'date'],
            'description' => ['nullable', 'string'],
        ];
        $rules['create'] = array_merge($rules['create'], $attributes);
        $rules['update'] = array_merge($rules['update'], $attributes);

        return $rules;
    }

    protected static function booted(): void
    {
        self::saving(static function (PoolTransaction $transaction): void {
            if ((int) $transaction->from_user_id === (int) $transaction->to_user_id) {
                throw ValidationException::withMessages([
                    'to_user_id' => ['Settlement participants must be different.'],
                ]);
            }
        });
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:4', 'occurred_on' => 'immutable_date', 'confirmed_at' => 'immutable_datetime'];
    }
}
