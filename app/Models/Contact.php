<?php

declare(strict_types=1);

namespace Modules\Business\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\User;
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
        'user_id',
        'name',
        'email',
        'phone',
    ];

    // protected static function newFactory(): ContactFactory
    // {
    //     // return ContactFactory::new();
    // }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User,$this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
