<?php

declare(strict_types=1);

namespace Modules\ERP\Policies;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Permission;
use Modules\Core\Models\User;

final class ERPModelPolicy
{
    public function view(User $user, Model $record): bool
    {
        return $this->allows($user, $record, 'select');
    }

    public function update(User $user, Model $record): bool
    {
        return $this->allows($user, $record, 'update');
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->allows($user, $record, 'delete');
    }

    public function restore(User $user, Model $record): bool
    {
        return $this->allows($user, $record, 'restore');
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return $this->allows($user, $record, 'forceDelete');
    }

    public function post(User $user, Model $record): bool
    {
        return $this->allows($user, $record, 'post');
    }

    public function unpost(User $user, Model $record): bool
    {
        return $this->allows($user, $record, 'unpost');
    }

    public function close(User $user, Model $record): bool
    {
        return $this->allows($user, $record, 'close');
    }

    public function reopen(User $user, Model $record): bool
    {
        return $this->allows($user, $record, 'reopen');
    }

    public function submitEInvoice(User $user, Model $record): bool
    {
        return $this->allows($user, $record, 'submitEInvoice');
    }

    public function refreshEInvoice(User $user, Model $record): bool
    {
        return $this->allows($user, $record, 'refreshEInvoice');
    }

    private function allows(User $user, Model $record, string $operation): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $permission = sprintf(
            '%s.%s.%s',
            $record->getConnectionName() ?? 'default',
            $record->getTable(),
            $operation,
        );

        if (! Permission::query()->where('name', $permission)->exists()) {
            return true;
        }

        $guard = config('auth.defaults.guard');

        return $user->hasPermissionTo($permission, is_string($guard) ? $guard : 'web');
    }
}
