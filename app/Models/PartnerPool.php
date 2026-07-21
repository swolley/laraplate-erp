<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\User;
use Modules\Core\Overrides\Model;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Models\Pivot\PartnerPoolHasUser;
use Override;

/**
 * @mixin \Eloquent
 * @mixin IdeHelperPartnerPool
 */
final class PartnerPool extends Model
{
    use BelongsToCompany;

    #[Override]
    protected $table = ERPTables::PartnerPools->value;

    #[Override]
    protected $fillable = ['company_id', 'name', 'currency', 'is_active'];

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, ERPTables::PartnerPoolMembers->value)
            ->using(PartnerPoolHasUser::class)->withTimestamps();
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(MovementAllocation::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PoolTransaction::class);
    }

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $attributes = [
            'company_id' => ['required', 'integer', 'exists:' . ERPTables::Companies->value . ',id'],
            'name' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'is_active' => ['sometimes', 'boolean'],
        ];
        $rules['create'] = array_merge($rules['create'], $attributes);
        $rules['update'] = array_merge($rules['update'], $attributes);

        return $rules;
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
