<?php

declare(strict_types=1);

namespace Modules\Business\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\Business\Database\Factories\MovementFactory;

/**
 * @mixin IdeHelperMovement
 */
class Movement extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'date',
    ];

    // protected static function newFactory(): MovementFactory
    // {
    //     // return MovementFactory::new();
    // }
}
