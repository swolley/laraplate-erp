<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Modules\Core\Helpers\HasActivation;
use Modules\Core\Overrides\Model;
use Modules\ERP\Concerns\BelongsToCompany;

/**
 * @mixin IdeHelperParty
 */
class Party extends Model
{
    use BelongsToCompany;
    use HasActivation;

    protected $table = 'parties';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'is_customer',
        'is_supplier',
    ];

    public function scopeCustomers(Builder $builder): Builder
    {
        return $builder->where('is_customer', true);
    }

    public function scopeSuppliers(Builder $builder): Builder
    {
        return $builder->where('is_supplier', true);
    }

    /**
     * @return BelongsToMany<Contact, $this>
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contactables', 'party_id')->withTimestamps();
    }

    public function tasks(): HasManyThrough
    {
        return $this->hasManyThrough(Task::class, Project::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    /**
     * @return HasMany<SalesOrder, $this>
     */
    public function sales_orders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    /**
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * @return HasMany<Opportunity, $this>
     */
    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'name' => ['required', 'string', 'max:255'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'name' => ['sometimes', 'string', 'max:255'],
        ]);

        return $rules;
    }
}
