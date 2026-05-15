<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Modules\Core\Overrides\Model;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Override;

// use Modules\ERP\Database\Factories\MovementFactory;

/**
 * @mixin \Eloquent
 * @mixin IdeHelperMovement
 */
final class Movement extends Model
{
    use BelongsToCompany;

    #[Override]
    protected $table = ERPTables::Movements->value;

    /**
     * The attributes that are mass assignable.
     */
    #[\Override]
    protected $fillable = [];

    // protected static function newFactory(): MovementFactory
    // {
    //     // return MovementFactory::new();
    // }
}
