<?php

declare(strict_types=1);

namespace Modules\Business\Models;

use Modules\Business\Concerns\BelongsToCompany;
use Modules\Core\Overrides\Model;

// use Modules\Business\Database\Factories\MovementFactory;

/**
 * @mixin IdeHelperMovement
 */
class Movement extends Model
{
    use BelongsToCompany;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    // protected static function newFactory(): MovementFactory
    // {
    //     // return MovementFactory::new();
    // }
}
