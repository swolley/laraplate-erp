<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Concerns\HasValidity;
use Modules\Core\Overrides\Model;
use Modules\ERP\Casts\ProjectStatus;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Override;

/**
 * @property int|string $id
 * @property int $company_id
 * @property int $party_id
 * @mixin \Eloquent
 * @mixin IdeHelperProject
 */
final class Project extends Model
{
    use BelongsToCompany;
    use HasValidity;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::Projects->value;

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
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

    /**
     * @return array<string, mixed>
     */

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'party_id' => ['required', 'integer', 'exists:' . ERPTables::Parties->value . ',id'],
            'quotation_id' => ['nullable', 'integer', 'exists:' . ERPTables::Quotations->value . ',id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', ProjectStatus::validationRule()],
            'version' => ['sometimes', 'integer', 'min:0', 'max:255'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'party_id' => ['sometimes', 'integer', 'exists:' . ERPTables::Parties->value . ',id'],
            'quotation_id' => ['nullable', 'integer', 'exists:' . ERPTables::Quotations->value . ',id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', ProjectStatus::validationRule()],
            'version' => ['sometimes', 'integer', 'min:0', 'max:255'],
        ]);

        return $rules;
    }

    protected static function booted(): void
    {
        self::saving(static function (Project $project): void {
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
}
