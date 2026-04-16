<?php

declare(strict_types=1);

namespace Modules\Business\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;

/**
 * @mixin IdeHelperContact
 */
class Contact extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'customer_id',
        'name',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'zip',
    ];

    // protected static function newFactory(): ContactFactory
    // {
    //     // return ContactFactory::new();
    // }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
