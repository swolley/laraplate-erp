<?php

declare(strict_types=1);

namespace Modules\Business\Models;

use Modules\Core\Overrides\Model;

// use Modules\Business\Database\Factories\MovementFactory;

/**
 * @mixin IdeHelperMovement
 */
class Movement extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    // protected static function newFactory(): MovementFactory
    // {
    //     // return MovementFactory::new();
    // }
}
