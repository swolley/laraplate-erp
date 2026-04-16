<?php

declare(strict_types=1);

namespace Modules\Business\Models;

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

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function tasks(): HasManyThrough
    {
        return $this->hasManyThrough(Task::class, Project::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }
}
