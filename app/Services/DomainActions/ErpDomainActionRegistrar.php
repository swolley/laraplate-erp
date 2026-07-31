<?php

declare(strict_types=1);

namespace Modules\ERP\Services\DomainActions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Modules\ERP\Services\Quotations\QuotationRevisionService;
use Modules\ERP\Services\Payments\SepaPain001Exporter;
use Modules\ERP\Services\Payments\PaymentRequestService;
use Modules\ERP\Services\Payments\CbiBonificiExporter;
use Modules\ERP\Services\Calendar\TaskIcsExporter;
use Modules\ERP\Services\Banking\BankStatementImportService;
use Modules\ERP\Services\Accounting\DocumentSequenceResetService;
use Modules\ERP\Models\Task;
use Modules\ERP\Models\Quotation;
use Modules\ERP\Models\PaymentRun;
use Modules\ERP\Models\PaymentRequest;
use Modules\ERP\Models\DocumentSequence;
use Modules\ERP\Models\BankStatement;
use Illuminate\Http\UploadedFile;
use Modules\Core\Services\Crud\DomainActionRegistry;
use Modules\ERP\Casts\EInvoiceSubmissionStatus;
use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Models\ReturnOrder;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Models\SupplierReturn;
use Modules\ERP\Services\Accounting\FiscalPeriodCloser;
use Modules\ERP\Services\Accounting\JournalPostingService;
use Modules\ERP\Services\EInvoice\EInvoiceSubmissionService;
use Modules\ERP\Services\Returns\ReturnOrderService;
use Modules\ERP\Services\Returns\SupplierReturnService;
use Modules\ERP\Services\SalesOrders\SalesOrderAmendmentService;

/**
 * Maps ERP domain actions onto the services that already implement them.
 *
 * Handlers stay thin on purpose: business rules, locking and state guards live
 * in the services and in {@see \Modules\ERP\Policies\ERPModelPolicy}, exactly as
 * they do for the Filament actions that drive the same code. A handler that
 * added a rule of its own would create a second truth.
 */
final class ErpDomainActionRegistrar
{
    public function register(DomainActionRegistry $registry): void
    {
        $this->registerInvoices($registry);
        $this->registerAccounting($registry);
        $this->registerReturns($registry);
        $this->registerCommercial($registry);
        $this->registerFileActions($registry);
    }

    /**
     * These return a Response, which CrudController passes through untouched.
     * Authorization and the state guard run before the first byte, so a refusal
     * is still a normal JSON error rather than a corrupt download.
     */
    private function registerFileActions(DomainActionRegistry $registry): void
    {
        $registry->register(PaymentRun::class, 'export_sepa', static function (Model $record, array $payload, User $user): StreamedResponse {
            $xml = resolve(SepaPain001Exporter::class)->export($record);

            return response()->streamDownload(
                static fn () => print ($xml),
                sprintf('payment-run-%d-sepa.xml', $record->getKey()),
                ['Content-Type' => 'application/xml'],
            );
        });

        $registry->register(PaymentRun::class, 'export_cbi_bonifici', static function (Model $record, array $payload, User $user): StreamedResponse {
            $content = resolve(CbiBonificiExporter::class)->export($record);

            return response()->streamDownload(
                static fn () => print ($content),
                sprintf('payment-run-%d-cbi.txt', $record->getKey()),
                ['Content-Type' => 'text/plain'],
            );
        });

        $registry->register(Task::class, 'export_ics', static function (Model $record, array $payload, User $user): StreamedResponse {
            $exporter = resolve(TaskIcsExporter::class);
            $content = $exporter->export($record);

            return response()->streamDownload(
                static fn () => print ($content),
                $exporter->fileName($record),
                ['Content-Type' => 'text/calendar'],
            );
        });

        $registry->register(BankStatement::class, 'import_file', static function (Model $record, array $payload, User $user): array {
            $file = request()->file('file');

            throw_if(
                ! $file instanceof UploadedFile,
                ValidationException::withMessages(['file' => ['An uploaded file is required.']]),
            );

            $format = is_string($payload['format'] ?? null) ? $payload['format'] : 'auto';

            return ['imported_lines' => resolve(BankStatementImportService::class)->importFile($record, $file->getRealPath(), $format)];
        });
    }

    /**
     * `approve` here redefines Core's generic verb. Both models declare it via
     * OverridesGenericCrudActions, which is what lets the registry accept it.
     */
    private function registerReturns(DomainActionRegistry $registry): void
    {
        $registry->register(ReturnOrder::class, 'approve', static fn (Model $record, array $payload, User $user): Model => resolve(ReturnOrderService::class)->approve($record));
        $registry->register(ReturnOrder::class, 'complete', static fn (Model $record, array $payload, User $user): Model => resolve(ReturnOrderService::class)->complete($record));
        $registry->register(ReturnOrder::class, 'cancel', static fn (Model $record, array $payload, User $user): Model => resolve(ReturnOrderService::class)->cancel($record));
        $registry->register(ReturnOrder::class, 'reverse_processed', static fn (Model $record, array $payload, User $user): Model => resolve(ReturnOrderService::class)->reverseProcessed($record));
        $registry->register(ReturnOrder::class, 'create_credit_note', static fn (Model $record, array $payload, User $user): Model => resolve(ReturnOrderService::class)->createCreditNote($record));

        $registry->register(SupplierReturn::class, 'approve', static fn (Model $record, array $payload, User $user): Model => resolve(SupplierReturnService::class)->approve($record));
        $registry->register(SupplierReturn::class, 'complete', static fn (Model $record, array $payload, User $user): Model => resolve(SupplierReturnService::class)->complete($record));
        $registry->register(SupplierReturn::class, 'cancel', static fn (Model $record, array $payload, User $user): Model => resolve(SupplierReturnService::class)->cancel($record));
        $registry->register(SupplierReturn::class, 'reverse_processed', static fn (Model $record, array $payload, User $user): Model => resolve(SupplierReturnService::class)->reverseProcessed($record));
        $registry->register(SupplierReturn::class, 'create_debit_note', static fn (Model $record, array $payload, User $user): Model => resolve(SupplierReturnService::class)->createDebitNote($record));
    }

    private function registerCommercial(DomainActionRegistry $registry): void
    {
        $registry->register(DocumentSequence::class, 'reset', static function (Model $record, array $payload, User $user): Model {
            resolve(DocumentSequenceResetService::class)->reset($record, (int) ($payload['last_number'] ?? 0));

            return $record->fresh();
        });

        $registry->register(
            Quotation::class,
            'create_revision',
            static fn (Model $record, array $payload, User $user): Model => resolve(QuotationRevisionService::class)->createRevision($record),
        );

        $registry->register(
            PaymentRequest::class,
            'send',
            static fn (Model $record, array $payload, User $user): Model => resolve(PaymentRequestService::class)->send($record),
        );

    }

    private function registerInvoices(DomainActionRegistry $registry): void
    {
        /**
         * Posting is driven by the observer watching `posted_at`, which is what
         * InvoicePostingActions does; InvoicePostingService is not the UI path.
         *
         * `force_post` is deliberately not an action of its own: it is a flag on
         * `post` carrying its own permission, mirroring the Filament form where
         * the checkbox appears only for a purchase invoice when the user holds
         * `forcePost`.
         */
        $registry->register(Invoice::class, 'post', static function (Model $record, array $payload, User $user): Model {
            $force = (bool) ($payload['force_three_way_match'] ?? false);

            throw_if(
                $force && ! $user->can('forcePost', $record),
                AuthorizationException::class,
                'User not allowed to force the three-way match',
            );

            $record->forceThreeWayMatchOnPosting = $force;
            $record->update(['posted_at' => now()]);

            return $record->fresh();
        });

        $registry->register(Invoice::class, 'unpost', static function (Model $record, array $payload, User $user): Model {
            $record->update(['posted_at' => null]);

            return $record->fresh();
        });

        $registry->register(
            Invoice::class,
            'submitEInvoice',
            static fn (Model $record, array $payload, User $user): Model => resolve(EInvoiceSubmissionService::class)->submit($record),
        );

        $registry->register(Invoice::class, 'refreshEInvoice', static function (Model $record, array $payload, User $user): Model {
            $submission = $record->eInvoiceSubmissions()
                ->where('status', EInvoiceSubmissionStatus::Submitted->value)
                ->whereNotNull('external_id')
                ->latest('id')
                ->firstOrFail();

            return resolve(EInvoiceSubmissionService::class)->refresh($submission);
        });
    }

    private function registerAccounting(DomainActionRegistry $registry): void
    {
        /**
         * reverse() needs the owning company and an explicit reason: a reversal
         * is an auditable accounting event, so it may not be anonymous.
         */
        $registry->register(JournalEntry::class, 'reverse', static function (Model $record, array $payload, User $user): Model {
            $reason = mb_trim((string) ($payload['reversal_reason'] ?? ''));

            throw_if(
                $reason === '',
                ValidationException::withMessages(['reversal_reason' => ['A reversal reason is required.']]),
            );

            return resolve(JournalPostingService::class)->reverse($record, $record->company, $reason, $user->id);
        });

        // FiscalPeriodCloser and the amendment service return void; hand back the
        // refreshed record so the response carries the resulting state.
        $registry->register(FiscalPeriod::class, 'close', static function (Model $record, array $payload, User $user): Model {
            resolve(FiscalPeriodCloser::class)->closePeriod($record);

            return $record->fresh();
        });

        $registry->register(FiscalPeriod::class, 'reopen', static function (Model $record, array $payload, User $user): Model {
            resolve(FiscalPeriodCloser::class)->reopenPeriod($record);

            return $record->fresh();
        });

        $registry->register(FiscalYear::class, 'close', static function (Model $record, array $payload, User $user): Model {
            resolve(FiscalPeriodCloser::class)->closeYear($record);

            return $record->fresh();
        });

        $registry->register(
            SalesOrder::class,
            'amend',
            static fn (Model $record, array $payload, User $user): Model => resolve(SalesOrderAmendmentService::class)->amend($record),
        );

        // Delivery notes post through the same observer-on-posted_at path as
        // DeliveryNotePostingActions.
        $registry->register(DeliveryNote::class, 'post', static function (Model $record, array $payload, User $user): Model {
            $record->update(['posted_at' => now()]);

            return $record->fresh();
        });

        $registry->register(DeliveryNote::class, 'unpost', static function (Model $record, array $payload, User $user): Model {
            $record->update(['posted_at' => null]);

            return $record->fresh();
        });
    }
}
