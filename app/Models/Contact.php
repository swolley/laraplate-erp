<?php

declare(strict_types=1);

namespace Modules\Business\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Business\Concerns\BelongsToCompany;
use Modules\Core\Models\User;
use Modules\Core\Overrides\Model;
use Override;

/**
 * @mixin IdeHelperContact
 */
class Contact extends Model
{
    use BelongsToCompany;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
    ];

    protected $hidden = [
        'user_id',
        'user',
        'created_at',
        'updated_at',
    ];

    private ?User $tempUser = null;

    /**
     * Customers this contact is linked to (pivot `contactables`).
     *
     * @return BelongsToMany<Customer, $this>
     */
    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'contactables')->withTimestamps();
    }

    /**
     * Hold a not-yet-persisted user to attach on first save (e.g. create user and contributor in one flow).
     */
    public function setTempUser(?User $user): void
    {
        $this->tempUser = $user;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(user_class());
    }

    #[Override]
    public function save(array $options = []): bool
    {
        if ($this->tempUser instanceof User && $this->tempUser->isDirty()) {
            $this->tempUser->save();
            $this->user_id = $this->tempUser->id;
            $this->tempUser = null;
            $this->load('user');
        } elseif ($this->user && $this->user->isDirty()) {
            $this->user->save();
        }

        return parent::save($options);
    }

    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:255'],
        ]);

        return $rules;
    }

    protected static function booted(): void
    {
        self::addGlobalScope(static fn (Builder $query) => $query->with('user'));
    }

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected function getCanLoginAttribute(): bool
    {
        return $this->user !== null || $this->tempUser instanceof User;
    }

    protected function isUserAttribute(): bool
    {
        return $this->getCanLoginAttribute();
    }
}
