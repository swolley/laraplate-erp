<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Modules\Core\Helpers\HasValidity;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\Overrides\Model;
use Modules\ERP\Casts\SalesOrderStatus;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Override;
use Overtrue\LaravelVersionable\VersionStrategy;

/**
 * Party sales order (M3.2) with optional links to a {@see Quotation} and {@see Project}.
 *
 * @mixin \Eloquent
 * @mixin IdeHelperSalesOrder
 */
final class SalesOrder extends Model
{
    use BelongsToCompany;
    use HasLocks;
    use HasValidity;

    #[Override]
    protected $table = ERPTables::SalesOrders->value;

    private VersionStrategy $versionStrategy = VersionStrategy::DIFF;

    #[\Override]
    protected $fillable = [
        'party_id',
        'quotation_id',
        'project_id',
        'amends_sales_order_id',
        'reference',
        'currency',
        'status',
        'notes',
    ];

    /**
     * @return BelongsTo<Party,$this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Party::class)->where('is_customer', true);
    }

    /**
     * @return BelongsTo<Quotation,$this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * @return BelongsTo<Project,$this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<SalesOrder,$this>
     */
    public function amended_from(): BelongsTo
    {
        return $this->belongsTo(self::class, 'amends_sales_order_id');
    }

    /**
     * @return HasMany<SalesOrder,$this>
     */
    public function amendments(): HasMany
    {
        return $this->hasMany(self::class, 'amends_sales_order_id');
    }

    /**
     * @return HasMany<SalesOrderLine,$this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class);
    }

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'company_id' => ['required', 'integer', 'exists:'.ERPTables::Companies->value.',id'],
            'party_id' => ['required', 'integer', 'exists:'.ERPTables::Parties->value.',id'],
            'quotation_id' => ['nullable', 'integer', 'exists:'.ERPTables::Quotations->value.',id'],
            'project_id' => ['nullable', 'integer', 'exists:'.ERPTables::Projects->value.',id'],
            'amends_sales_order_id' => ['nullable', 'integer', 'exists:'.ERPTables::SalesOrders->value.',id'],
            'reference' => ['nullable', 'string', 'max:64'],
            'currency' => ['required', 'string', 'size:3'],
            'status' => ['required', 'string', SalesOrderStatus::validationRule()],
            'notes' => ['nullable', 'string'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'party_id' => ['sometimes', 'integer', 'exists:'.ERPTables::Parties->value.',id'],
            'quotation_id' => ['nullable', 'integer', 'exists:'.ERPTables::Quotations->value.',id'],
            'project_id' => ['nullable', 'integer', 'exists:'.ERPTables::Projects->value.',id'],
            'amends_sales_order_id' => ['nullable', 'integer', 'exists:'.ERPTables::SalesOrders->value.',id'],
            'reference' => ['nullable', 'string', 'max:64'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'status' => ['sometimes', 'string', SalesOrderStatus::validationRule()],
            'notes' => ['nullable', 'string'],
        ]);

        return $rules;
    }

    protected static function booted(): void
    {
        self::saving(static function (SalesOrder $order): void {
            if ($order->party_id !== null) {
                $party = Party::query()->find($order->party_id);

                if ($party !== null && ! $party->is_customer) {
                    throw ValidationException::withMessages([
                        'party_id' => ['The selected party must be a customer.'],
                    ]);
                }
            }

            if ($order->exists && $order->isHeaderLocked() && $order->isDirty([
                'party_id',
                'quotation_id',
                'project_id',
                'reference',
                'currency',
            ])) {
                throw ValidationException::withMessages([
                    'status' => ['Confirmed/evaded orders have a locked header and cannot change key fields.'],
                ]);
            }

            if ($order->quotation_id !== null) {
                $quotation = Quotation::query()->find($order->quotation_id);

                if ($quotation === null) {
                    throw ValidationException::withMessages([
                        'quotation_id' => ['The selected quotation is invalid.'],
                    ]);
                }

                if ((int) $quotation->party_id !== (int) $order->party_id) {
                    throw ValidationException::withMessages([
                        'quotation_id' => ['The quotation must belong to the same party as this order.'],
                    ]);
                }

                if ((int) $quotation->company_id !== (int) $order->company_id) {
                    throw ValidationException::withMessages([
                        'quotation_id' => ['The quotation must belong to the same company as this order.'],
                    ]);
                }
            }

            if ($order->project_id !== null) {
                $project = Project::query()->find($order->project_id);

                if ($project === null) {
                    throw ValidationException::withMessages([
                        'project_id' => ['The selected project is invalid.'],
                    ]);
                }

                if ((int) $project->party_id !== (int) $order->party_id) {
                    throw ValidationException::withMessages([
                        'project_id' => ['The project must belong to the same party as this order.'],
                    ]);
                }

                if ((int) $project->company_id !== (int) $order->company_id) {
                    throw ValidationException::withMessages([
                        'project_id' => ['The project must belong to the same company as this order.'],
                    ]);
                }
            }

            if ($order->exists
                && $order->amends_sales_order_id !== null
                && (int) $order->amends_sales_order_id === (int) $order->getKey()) {
                throw ValidationException::withMessages([
                    'amends_sales_order_id' => ['An order cannot amend itself.'],
                ]);
            }
        });

        self::saved(static function (SalesOrder $order): void {
            if ($order->status !== SalesOrderStatus::Confirmed) {
                return;
            }

            if ($order->quotation_id === null) {
                return;
            }

            $quotation = Quotation::query()->find($order->quotation_id);

            if ($quotation === null || $quotation->isLocked()) {
                return;
            }

            $quotation->lock();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => SalesOrderStatus::class,
        ];
    }

    protected function shouldVersioning(): bool
    {
        return false;
    }

    private function isHeaderLocked(): bool
    {
        return in_array($this->status, [
            SalesOrderStatus::Confirmed,
            SalesOrderStatus::PartiallyEvased,
            SalesOrderStatus::FullyEvased,
        ], true);
    }
}
