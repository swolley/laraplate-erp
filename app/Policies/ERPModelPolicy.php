<?php

declare(strict_types=1);

namespace Modules\ERP\Policies;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Permission;
use Modules\Core\Models\User;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\SalesOrderStatus;
use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Models\SalesOrder;

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
        return $this->allowsDomainAction($user, $record, 'post', static function (Model $record): bool {
            if ($record instanceof Invoice) {
                return $record->journal_entry_id === null;
            }

            if ($record instanceof DeliveryNote) {
                return $record->posted_at === null;
            }

            return true;
        });
    }

    public function unpost(User $user, Model $record): bool
    {
        return $this->allowsDomainAction($user, $record, 'unpost', static function (Model $record): bool {
            if ($record instanceof Invoice) {
                return $record->journal_entry_id !== null;
            }

            if ($record instanceof DeliveryNote) {
                return $record->posted_at !== null;
            }

            return true;
        });
    }

    public function close(User $user, Model $record): bool
    {
        return $this->allowsDomainAction($user, $record, 'close', static function (Model $record): bool {
            if ($record instanceof FiscalPeriod || $record instanceof FiscalYear) {
                return ! $record->is_closed;
            }

            return true;
        });
    }

    public function reopen(User $user, Model $record): bool
    {
        return $this->allowsDomainAction($user, $record, 'reopen', static function (Model $record): bool {
            if ($record instanceof FiscalPeriod) {
                return $record->is_closed;
            }

            return false;
        });
    }

    public function reverse(User $user, Model $record): bool
    {
        return $this->allowsDomainAction($user, $record, 'reverse', static function (Model $record): bool {
            if (! $record instanceof JournalEntry) {
                return false;
            }

            if ($record->posted_at === null) {
                return false;
            }

            return ! $record->reversal_voucher()->exists();
        });
    }

    public function amend(User $user, Model $record): bool
    {
        return $this->allowsDomainAction($user, $record, 'amend', static function (Model $record): bool {
            if (! $record instanceof SalesOrder) {
                return false;
            }

            return in_array($record->status, [SalesOrderStatus::Confirmed, SalesOrderStatus::PartiallyEvased], true);
        });
    }

    public function forcePost(User $user, Model $record): bool
    {
        return $this->allowsDomainAction($user, $record, 'force_post', static function (Model $record): bool {
            if (! $record instanceof Invoice) {
                return false;
            }

            return $record->direction === InvoiceDirection::Purchase
                && $record->journal_entry_id === null;
        });
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

        return $this->hasPermission($user, $record, $operation);
    }

    /**
     * @param  callable(Model): bool  $state_allows
     */
    private function allowsDomainAction(User $user, Model $record, string $operation, callable $state_allows): bool
    {
        if (! $state_allows($record)) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $this->hasPermission($user, $record, $operation);
    }

    private function hasPermission(User $user, Model $record, string $operation): bool
    {
        $permission = sprintf(
            '%s.%s.%s',
            $record->getConnectionName() ?? 'default',
            $record->getTable(),
            $operation,
        );

        if (! Permission::query()->where('name', $permission)->exists()) {
            return false;
        }

        $guard = config('auth.defaults.guard');

        return $user->hasPermissionTo($permission, is_string($guard) ? $guard : 'web');
    }
}
