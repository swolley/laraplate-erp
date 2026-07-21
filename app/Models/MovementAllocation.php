<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\User;
use Modules\Core\Overrides\Model;
use Modules\ERP\Enums\ERPTables;
use Override;

/**
 * @mixin \Eloquent
 * @mixin IdeHelperMovementAllocation
 */
final class MovementAllocation extends Model
{
    #[Override]
    protected $table = ERPTables::MovementAllocations->value;

    #[Override]
    protected $fillable = ['partner_pool_id', 'movement_id', 'user_id', 'owed_amount', 'paid_amount'];

    public function partner_pool(): BelongsTo
    {
        return $this->belongsTo(PartnerPool::class);
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(Movement::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $attributes = [
            'partner_pool_id' => ['required', 'integer', 'exists:' . ERPTables::PartnerPools->value . ',id'],
            'movement_id' => ['required', 'integer', 'exists:' . ERPTables::Movements->value . ',id'],
            'user_id' => ['required', 'integer'],
            'owed_amount' => ['required', 'numeric', 'min:0'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
        ];
        $rules['create'] = array_merge($rules['create'], $attributes);
        $rules['update'] = array_merge($rules['update'], $attributes);

        return $rules;
    }

    protected function casts(): array
    {
        return ['owed_amount' => 'decimal:4', 'paid_amount' => 'decimal:4'];
    }
}
