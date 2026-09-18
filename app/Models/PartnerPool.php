<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Contracts\IActivatableModel;
use Modules\Core\Models\Concerns\HasActivation;
use Modules\Core\Models\User;
use Modules\Core\Overrides\Model;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Models\Pivot\PartnerPoolHasUser;
use Override;

final class PartnerPool extends Model implements IActivatableModel
{
    use BelongsToCompany, HasActivation;

    #[Override]
    protected $table = ERPTables::PartnerPools->value;

    #[Override]
    protected $fillable = ['company_id', 'name', 'currency', 'is_active'];

    // Deliberately unannotated: declaring BelongsToMany<User, $this> turns on
    // Larastan's column check, which reads the qualified `users.id` this relation
    // plucks (to disambiguate it from the pivot) as a column User does not have.
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, ERPTables::PartnerPoolMembers->value)
            ->using(PartnerPoolHasUser::class)->withTimestamps();
    }

    /**
     * @return HasMany<MovementAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(MovementAllocation::class);
    }

    /**
     * @return HasMany<PoolTransaction, $this>
     */
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
