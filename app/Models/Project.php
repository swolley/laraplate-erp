<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
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
        'party_id',
        'quotation_id',
        'name',
        'description',
        'status',
        'version',
    ];

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
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

    protected static function booted(): void
    {
        static::saving(static function (Project $project): void {
            if ($project->party_id === null) {
                return;
            }

            $party = Party::query()->find($project->party_id);

            if ($party !== null && ! $party->is_customer) {
                throw ValidationException::withMessages([
                    'party_id' => ['The selected party must be a customer.'],
                ]);
            }
        });
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
            'party_id' => ['required', 'integer', 'exists:parties,id'],
            'quotation_id' => ['nullable', 'integer', 'exists:quotations,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', ProjectStatus::validationRule()],
            'version' => ['sometimes', 'integer', 'min:0', 'max:255'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'party_id' => ['sometimes', 'integer', 'exists:parties,id'],
            'quotation_id' => ['nullable', 'integer', 'exists:quotations,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', ProjectStatus::validationRule()],
            'version' => ['sometimes', 'integer', 'min:0', 'max:255'],
        ]);

        return $rules;
    }
}
