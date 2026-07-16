<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Modules\Core\Models\Concerns\HasActivation;
use Modules\Core\Overrides\Model;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Models\Pivot\Contactable;
use Override;

/**
 * @property int|string $id
 * @property int $company_id
 * @property string $name
 * @property bool $is_customer
 * @property bool $is_supplier
 * @mixin \Eloquent
 * @mixin IdeHelperParty
 */
final class Party extends Model
{
    use BelongsToCompany;
    use HasActivation;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::Parties->value;

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
    protected $fillable = [
        'name',
        'tax_id',
        'vat_number',
        'fiscal_country',
        'address_line',
        'postal_code',
        'city',
        'province',
        'country',
        'einvoice_recipient_code',
        'einvoice_pec_email',
        'is_customer',
        'is_supplier',
    ];

    /**
     * @return BelongsToMany<Contact, $this>
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, ERPTables::Contactables->value, 'party_id', 'contact_id')
            ->using(Contactable::class)
            ->withTimestamps();
    }

    /**
     * @return HasManyThrough<Task, Project, $this>
     */
    public function tasks(): HasManyThrough
    {
        return $this->hasManyThrough(Task::class, Project::class);
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * @return HasMany<Quotation, $this>
     */
    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    /**
     * @return HasMany<SalesOrder, $this>
     */
    public function sales_orders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    /**
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * @return HasMany<Opportunity, $this>
     */
    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    /**
     * @return HasMany<PartyPriceRule, $this>
     */
    public function price_rules(): HasMany
    {
        return $this->hasMany(PartyPriceRule::class);
    }

    /**
     * @return HasMany<PartyBankAccount, $this>
     */
    public function bank_accounts(): HasMany
    {
        return $this->hasMany(PartyBankAccount::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'name' => ['required', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:32'],
            'vat_number' => ['nullable', 'string', 'max:32'],
            'fiscal_country' => ['nullable', 'string', 'size:2'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:16'],
            'city' => ['nullable', 'string', 'max:128'],
            'province' => ['nullable', 'string', 'max:8'],
            'country' => ['nullable', 'string', 'size:2'],
            'einvoice_recipient_code' => ['nullable', 'string', 'max:7'],
            'einvoice_pec_email' => ['nullable', 'email', 'max:255'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'name' => ['sometimes', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:32'],
            'vat_number' => ['nullable', 'string', 'max:32'],
            'fiscal_country' => ['nullable', 'string', 'size:2'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:16'],
            'city' => ['nullable', 'string', 'max:128'],
            'province' => ['nullable', 'string', 'max:8'],
            'country' => ['nullable', 'string', 'size:2'],
            'einvoice_recipient_code' => ['nullable', 'string', 'max:7'],
            'einvoice_pec_email' => ['nullable', 'email', 'max:255'],
        ]);

        return $rules;
    }

    /**
     * @param  Builder<Party>  $builder
     * @return Builder<Party>
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function customers(Builder $builder): Builder
    {
        return $builder->where('is_customer', true);
    }

    /**
     * @param  Builder<Party>  $builder
     * @return Builder<Party>
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function suppliers(Builder $builder): Builder
    {
        return $builder->where('is_supplier', true);
    }
}
