<?php

declare(strict_types=1);

namespace Modules\ERP\Models\Pivot;

use Modules\Core\Overrides\Pivot;
use Modules\ERP\Enums\ERPTables;
use Override;

/**
 * Pivot linking ERP parties and contacts.
 *
 * @property int $party_id
 * @property int $contact_id
 * @mixin \Eloquent
 * @mixin IdeHelperContactable
 */
final class Contactable extends Pivot
{
    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::Contactables->value;

    #[Override]
    public $incrementing = false;

    #[Override]
    public $timestamps = true;

    #[Override]
    protected $fillable = [
        'party_id',
        'contact_id',
    ];

    protected function casts(): array
    {
        return [
            'party_id' => 'integer',
            'contact_id' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'datetime',
        ];
    }
}
