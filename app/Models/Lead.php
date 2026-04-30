<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\User;
use Modules\Core\Overrides\Model;
use Modules\ERP\Casts\LeadStatus;
use Modules\ERP\Concerns\BelongsToCompany;
use Override;
use Overtrue\LaravelVersionable\VersionStrategy;

/**
 * CRM lead (M3.1): early-stage prospect before a formal {@see Opportunity}.
 *
 * Uses {@see Customer} / {@see Contact} FKs instead of a unified {@code parties} table until the purchasing-cycle migration lands.
 *
 * @mixin IdeHelperLead
 */
class Lead extends Model
{
    use BelongsToCompany;

    protected VersionStrategy $versionStrategy = VersionStrategy::DIFF;

    protected $fillable = [
        'company_id',
        'customer_id',
        'contact_id',
        'title',
        'source',
        'status',
        'owner_user_id',
        'notes',
        'converted_at',
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(user_class(), 'owner_user_id');
    }

    /**
     * @return HasMany<Opportunity, $this>
     */
    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'title' => ['required', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:128'],
            'status' => ['required', 'string', LeadStatus::validationRule()],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'converted_at' => ['nullable', 'date'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:128'],
            'status' => ['sometimes', 'string', LeadStatus::validationRule()],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'converted_at' => ['nullable', 'date'],
        ]);

        return $rules;
    }

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'converted_at' => 'immutable_datetime',
        ];
    }
}
