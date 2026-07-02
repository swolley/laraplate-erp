<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Overrides\Model;
use Modules\ERP\Casts\AccountKind;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Override;
use Overtrue\LaravelVersionable\VersionStrategy;

/**
 * General ledger account node (chart of accounts).
 *
 * @property int|string $id
 * @property int $company_id
 * @mixin \Eloquent
 * @mixin IdeHelperAccount
 */
final class Account extends Model
{
    use BelongsToCompany;

    /**
     * Accounting models always version with DIFF; overrides any Setting row.
     */
    protected VersionStrategy $versionStrategy = VersionStrategy::DIFF;

    #[Override]
    protected $table = ERPTables::Accounts->value;

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
    protected $fillable = [
        'company_id',
        'code',
        'name',
        'kind',
        'parent_id',
        'meta',
        'is_active',
    ];

    /**
     * @return BelongsTo<Account, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Account, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return array<string, mixed>
     */

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'company_id' => ['required', 'integer', 'exists:' . ERPTables::Companies->value . ',id'],
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', 'string', AccountKind::validationRule()],
            'parent_id' => ['nullable', 'integer', 'exists:' . ERPTables::Accounts->value . ',id'],
            'meta' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'code' => ['sometimes', 'string', 'max:32'],
            'name' => ['sometimes', 'string', 'max:255'],
            'kind' => ['sometimes', 'string', AccountKind::validationRule()],
            'parent_id' => ['nullable', 'integer', 'exists:' . ERPTables::Accounts->value . ',id'],
            'meta' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return $rules;
    }

    protected function casts(): array
    {
        return [
            'kind' => AccountKind::class,
            'meta' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
