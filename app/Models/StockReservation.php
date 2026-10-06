<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Contracts\RestrictsCrudWrites;
use Modules\Core\Models\Concerns\DeniesGenericCrudWrites;
use Modules\Core\Overrides\Model;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Database\Factories\StockReservationFactory;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Enums\StockReservationState;
use Override;

/**
 * A quantity of an item held back for a document that ERP does not know about.
 *
 * `source_type` / `source_id` are deliberately opaque columns, not a morph relation: the reserving
 * document may live in a module ERP must never import or resolve, so there is no `source()`.
 */
final class StockReservation extends Model implements RestrictsCrudWrites
{
    use BelongsToCompany;
    use DeniesGenericCrudWrites;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::StockReservations->value;

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
    protected $fillable = [
        'company_id',
        'item_id',
        'warehouse_id',
        'source_type',
        'source_id',
        'quantity',
        'state',
        'expires_at',
    ];

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'source_type' => ['required', 'string', 'max:255'],
            'source_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'state' => ['required', 'string', StockReservationState::validationRule()],
            'expires_at' => ['nullable', 'date'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'source_type' => ['sometimes', 'string', 'max:255'],
            'source_id' => ['sometimes', 'integer', 'min:1'],
            'quantity' => ['sometimes', 'numeric', 'min:0.0001'],
            'state' => ['sometimes', 'string', StockReservationState::validationRule()],
            'expires_at' => ['nullable', 'date'],
        ]);

        return $rules;
    }

    /**
     * @return Factory<self>
     */
    #[Override]
    protected static function newFactory(): Factory
    {
        return StockReservationFactory::new();
    }

    /**
     * Restrict the query to reservations that still hold quantity back (soft or hard).
     *
     * @param  Builder<StockReservation>  $query
     * @return Builder<StockReservation>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->whereIn('state', [StockReservationState::Soft, StockReservationState::Hard]);
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'state' => StockReservationState::class,
            'quantity' => 'decimal:4',
            'expires_at' => 'datetime',
        ];
    }

    protected function shouldVersioning(): bool
    {
        return false;
    }
}
