<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Models\User;
use Modules\Core\Overrides\Model;
use Modules\ERP\Casts\LeadStatus;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Override;
use Overtrue\LaravelVersionable\VersionStrategy;

/**
 * CRM lead (M3.1): early-stage prospect before a formal {@see Opportunity}.
 *
 * @mixin \Eloquent
 * @mixin IdeHelperLead
 */
final class Lead extends Model
{
    use BelongsToCompany;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::Leads->value;

    private VersionStrategy $versionStrategy = VersionStrategy::DIFF;

    /**
     * The attributes that are mass assignable.
     */
    #[\Override]
    protected $fillable = [
        'company_id',
        'party_id',
        'contact_id',
        'title',
        'source',
        'status',
        'owner_user_id',
        'notes',
        'converted_at',
    ];

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
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
            'company_id' => ['required', 'integer', 'exists:' . ERPTables::Companies->value . ',id'],
            'party_id' => ['nullable', 'integer', 'exists:' . ERPTables::Parties->value . ',id'],
            'contact_id' => ['nullable', 'integer', 'exists:' . ERPTables::Contacts->value . ',id'],
            'title' => ['required', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:128'],
            'status' => ['required', 'string', LeadStatus::validationRule()],
            'owner_user_id' => ['nullable', 'integer', 'exists:' . CoreTables::Users->value . ',id'],
            'notes' => ['nullable', 'string'],
            'converted_at' => ['nullable', 'date'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'party_id' => ['nullable', 'integer', 'exists:' . ERPTables::Parties->value . ',id'],
            'contact_id' => ['nullable', 'integer', 'exists:' . ERPTables::Contacts->value . ',id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:128'],
            'status' => ['sometimes', 'string', LeadStatus::validationRule()],
            'owner_user_id' => ['nullable', 'integer', 'exists:' . CoreTables::Users->value . ',id'],
            'notes' => ['nullable', 'string'],
            'converted_at' => ['nullable', 'date'],
        ]);

        return $rules;
    }

    protected static function booted(): void
    {
        self::saving(static function (Lead $lead): void {
            if ($lead->party_id === null) {
                return;
            }

            $party = Party::query()->find($lead->party_id);

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
            'status' => LeadStatus::class,
            'converted_at' => 'immutable_datetime',
        ];
    }
}
