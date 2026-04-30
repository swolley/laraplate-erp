<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\ERP\Casts\ProjectStatus;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\Core\Helpers\HasValidity;
use Modules\Core\Overrides\Model;
use Override;

/**
 * @mixin IdeHelperProject
 */
class Project extends Model
{
    use BelongsToCompany;
    use HasValidity;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'customer_id',
        'quotation_id',
        'name',
        'description',
        'status',
        'version',
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
     * @return HasMany<SalesOrder, $this>
     */
    public function sales_orders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * @return HasMany<TimeEntry, $this>
     */
    public function time_entries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'version' => 'integer',
        ];
    }

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'quotation_id' => ['nullable', 'integer', 'exists:quotations,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', ProjectStatus::validationRule()],
            'version' => ['sometimes', 'integer', 'min:0', 'max:255'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
            'quotation_id' => ['nullable', 'integer', 'exists:quotations,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', ProjectStatus::validationRule()],
            'version' => ['sometimes', 'integer', 'min:0', 'max:255'],
        ]);

        return $rules;
    }
}
