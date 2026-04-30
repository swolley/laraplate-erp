<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Helpers\HasValidity;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\Overrides\Model;
use Modules\ERP\Casts\SalesOrderStatus;
use Modules\ERP\Concerns\BelongsToCompany;
use Override;
use Overtrue\LaravelVersionable\VersionStrategy;

/**
 * Customer sales order (M3.2) with optional links to a {@see Quotation} and {@see Project}.
 *
 * @mixin IdeHelperSalesOrder
 */
class SalesOrder extends Model
{
    use BelongsToCompany;
    use HasLocks;
    use HasValidity;

    protected VersionStrategy $versionStrategy = VersionStrategy::DIFF;

    protected $fillable = [
        'customer_id',
        'quotation_id',
        'project_id',
        'amends_sales_order_id',
        'reference',
        'currency',
        'status',
        'notes',
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<SalesOrder, $this>
     */
    public function amended_from(): BelongsTo
    {
        return $this->belongsTo(self::class, 'amends_sales_order_id');
    }

    /**
     * @return HasMany<SalesOrder, $this>
     */
    public function amendments(): HasMany
    {
        return $this->hasMany(self::class, 'amends_sales_order_id');
    }

    /**
     * @return HasMany<SalesOrderLine, $this>
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
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'quotation_id' => ['nullable', 'integer', 'exists:quotations,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'amends_sales_order_id' => ['nullable', 'integer', 'exists:sales_orders,id'],
            'reference' => ['nullable', 'string', 'max:64'],
            'currency' => ['required', 'string', 'size:3'],
            'status' => ['required', 'string', SalesOrderStatus::validationRule()],
            'notes' => ['nullable', 'string'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
            'quotation_id' => ['nullable', 'integer', 'exists:quotations,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'amends_sales_order_id' => ['nullable', 'integer', 'exists:sales_orders,id'],
            'reference' => ['nullable', 'string', 'max:64'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'status' => ['sometimes', 'string', SalesOrderStatus::validationRule()],
            'notes' => ['nullable', 'string'],
        ]);

        return $rules;
    }

    protected function casts(): array
    {
        return [
            'status' => SalesOrderStatus::class,
        ];
    }
}
