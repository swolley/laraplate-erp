<?php

declare(strict_types=1);

namespace Modules\ERP\Policies;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Permission;
use Modules\Core\Models\User;
use Modules\Core\Support\PermissionName;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\ReturnStatus;
use Modules\ERP\Casts\SalesOrderStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Models\DocumentSequence;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Models\Quotation;
use Modules\ERP\Models\ReturnOrder;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Models\SupplierReturn;
use Modules\ERP\Models\TaxCode;

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

    public function unlock(User $user, Model $record): bool
    {
        return $this->allowsDomainAction($user, $record, 'unlock', static function (Model $record): bool {
            if (! $record instanceof Quotation) {
                return false;
            }

            return $record->isLocked();
        });
    }

    /**
     * Redefines Core's generic `approve`, which votes on a pending Modification.
     * Here it advances a return from Draft to Approved; the two never coexist on
     * one entity, and {@see \Modules\Core\Services\Crud\DomainActionRegistry}
     * enforces that at boot.
     */
    public function approve(User $user, Model $record): bool
    {
        return $this->allowsDomainAction($user, $record, 'approve', static function (Model $record): bool {
            if (! $record instanceof ReturnOrder && ! $record instanceof SupplierReturn) {
                return false;
            }

            return $record->status === ReturnStatus::Draft;
        });
    }

    public function complete(User $user, Model $record): bool
    {
        return $this->allowsDomainAction($user, $record, 'complete', static function (Model $record): bool {
            if (! $record instanceof ReturnOrder && ! $record instanceof SupplierReturn) {
                return false;
            }

            return $record->status === ReturnStatus::Approved;
        });
    }

    public function cancel(User $user, Model $record): bool
    {
        return $this->allowsDomainAction($user, $record, 'cancel', static function (Model $record): bool {
            if (! $record instanceof ReturnOrder && ! $record instanceof SupplierReturn) {
                return false;
            }

            return in_array($record->status, [ReturnStatus::Draft, ReturnStatus::Approved], true);
        });
    }

    /**
     * A processed return can be reversed only while no fiscal note references it:
     * the note is the auditable document, and unlinking it silently is not an
     * option.
     */
    public function reverseProcessed(User $user, Model $record): bool
    {
        return $this->allowsDomainAction($user, $record, 'reverse_processed', static function (Model $record): bool {
            if ($record instanceof ReturnOrder) {
                return $record->status === ReturnStatus::Processed
                    && $record->credit_note_invoice_id === null;
            }

            if ($record instanceof SupplierReturn) {
                return $record->status === ReturnStatus::Processed
                    && $record->debit_note_invoice_id === null;
            }

            return false;
        });
    }

    public function createCreditNote(User $user, Model $record): bool
    {
        return $this->allowsDomainAction($user, $record, 'create_credit_note', static function (Model $record): bool {
            if (! $record instanceof ReturnOrder) {
                return false;
            }

            return $record->status === ReturnStatus::Processed
                && $record->invoice_id !== null
                && $record->credit_note_invoice_id === null;
        });
    }

    public function createDebitNote(User $user, Model $record): bool
    {
        return $this->allowsDomainAction($user, $record, 'create_debit_note', static function (Model $record): bool {
            if (! $record instanceof SupplierReturn) {
                return false;
            }

            return $record->status === ReturnStatus::Processed
                && $record->purchase_order_id !== null
                && $record->debit_note_invoice_id === null;
        });
    }

    public function reset(User $user, Model $record): bool
    {
        return $this->allowsDomainAction($user, $record, 'reset', static fn (Model $record): bool => $record instanceof DocumentSequence);
    }

    public function reserve(User $user, Model $record): bool
    {
        return $this->allowsDomainAction($user, $record, 'reserve', static fn (Model $record): bool => $record instanceof DocumentSequence);
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

    public function supersede(User $user, Model $record): bool
    {
        return $this->allowsDomainAction($user, $record, 'supersede', static fn (Model $record): bool => $record instanceof TaxCode);
    }

    public function switchContext(User $user, Model $record): bool
    {
        return $this->allowsDomainAction($user, $record, 'switch_context', static fn (Model $record): bool => $record instanceof Company);
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
        $permission = PermissionName::forModel($record, $operation);

        if (! Permission::query()->where('name', $permission)->exists()) {
            return false;
        }

        $guard = config('auth.defaults.guard');

        return $user->hasPermissionTo($permission, is_string($guard) ? $guard : 'web');
    }
}
