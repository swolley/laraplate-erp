<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Modules\ERP\Concerns\BelongsToCompany;
use Modules\Core\Overrides\Model;

// use Modules\ERP\Database\Factories\MovementFactory;

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
