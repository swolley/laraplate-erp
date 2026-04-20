<?php

declare(strict_types=1);

namespace Modules\Business\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Modules\Core\Helpers\HasActivation;
use Modules\Core\Overrides\Model;

// use Modules\Business\Database\Factories\CustomerFactory;

/**
 * @mixin IdeHelperCustomer
 */
class Customer extends Model
{
    use HasActivation;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
    ];

    // protected static function newFactory(): CustomerFactory
    // {
    //     // return CustomerFactory::new();
    // }

    /**
     * @return BelongsToMany<Contact, $this>
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contactables')->withTimestamps();
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
