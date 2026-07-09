<?php

declare(strict_types=1);

namespace Modules\ERP\Providers;

use Illuminate\Support\Facades\Gate;
use Modules\ERP\Contracts\ChartOfAccountsProvider;
use Modules\ERP\Contracts\CurrencyConverter;
use Modules\ERP\Contracts\EInvoiceProvider;
use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Models\DocumentSequence;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Models\Quotation;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Policies\ERPModelPolicy;
use Modules\ERP\Services\Accounting\ChartOfAccountsInstaller;
use Modules\ERP\Services\Accounting\DocumentNumberAllocator;
use Modules\ERP\Services\Accounting\FiscalCalendarInstaller;
use Modules\ERP\Services\Accounting\FiscalPeriodCloser;
use Modules\ERP\Services\Accounting\InvoiceDeliveryNoteValidationService;
use Modules\ERP\Services\Accounting\InvoicePostingService;
use Modules\ERP\Services\Accounting\ItalianCoaProvider;
use Modules\ERP\Services\Accounting\JournalPostingService;
use Modules\ERP\Services\Banking\BankReconciliationService;
use Modules\ERP\Services\Banking\BankStatementCsvImporter;
use Modules\ERP\Services\Company\ErpCompanySettings;
use Modules\ERP\Services\Currency\NoopCurrencyConverter;
use Modules\ERP\Services\EInvoice\EInvoiceSubmissionService;
use Modules\ERP\Services\EInvoice\StubEInvoiceProvider;
use Modules\ERP\Services\Inventory\DeliveryNoteCogsJournalService;
use Modules\ERP\Services\Inventory\DeliveryNoteInventoryService;
use Modules\ERP\Services\Inventory\GoodsReceiptInventoryService;
use Modules\ERP\Services\Inventory\StockMovementService;
use Modules\ERP\Services\Pricing\PriceResolverService;
use Modules\ERP\Services\Purchasing\ThreeWayMatchService;
use Modules\ERP\Services\Returns\CustomerReturnReceiptService;
use Modules\ERP\Services\Returns\ReturnOrderService;
use Modules\ERP\Services\Returns\SupplierReturnService;
use Modules\ERP\Services\Returns\SupplierReturnShipmentService;
use Modules\ERP\Services\SalesOrders\SalesOrderAmendmentService;
use Modules\ERP\Services\Taxation\TaxCodeSupersessionService;
use Modules\ERP\Services\Taxation\TaxLineCalculator;
use Nwidart\Modules\Support\ModuleServiceProvider;
use Override;

class ERPServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the modulce.
     */
    #[Override]
    protected string $name = 'ERP';

    /**
     * The lowercase version of the module name.
     */
    #[Override]
    protected string $nameLower = 'erp';

    #[Override]
    public function boot(): void
    {
        parent::boot();

        foreach ($this->policyModels() as $model) {
            Gate::policy($model, ERPModelPolicy::class);
        }
    }

    #[Override]
    public function register(): void
    {
        parent::register();

        $this->app->singleton(CurrencyConverter::class, NoopCurrencyConverter::class);
        $this->app->bind(EInvoiceProvider::class, function (\Illuminate\Contracts\Foundation\Application $app): EInvoiceProvider {
            return match (config('erp.einvoice.driver', 'stub')) {
                'stub' => $app->make(StubEInvoiceProvider::class),
                default => $app->make(StubEInvoiceProvider::class),
            };
        });
        $this->app->singleton(ChartOfAccountsProvider::class, ItalianCoaProvider::class);
        $this->app->singleton(ChartOfAccountsInstaller::class, fn (\Illuminate\Contracts\Foundation\Application $app): ChartOfAccountsInstaller => new ChartOfAccountsInstaller(
            $app->make(ChartOfAccountsProvider::class),
        ));
        $this->app->singleton(FiscalCalendarInstaller::class);
        $this->app->singleton(FiscalPeriodCloser::class);
        $this->app->singleton(DocumentNumberAllocator::class);
        $this->app->singleton(JournalPostingService::class);
        $this->app->singleton(InvoiceDeliveryNoteValidationService::class);
        $this->app->singleton(ErpCompanySettings::class);
        $this->app->singleton(ThreeWayMatchService::class);
        $this->app->singleton(InvoicePostingService::class);
        $this->app->singleton(TaxLineCalculator::class);
        $this->app->singleton(TaxCodeSupersessionService::class);
        $this->app->singleton(StockMovementService::class);
        $this->app->singleton(DeliveryNoteCogsJournalService::class);
        $this->app->singleton(DeliveryNoteInventoryService::class);
        $this->app->singleton(GoodsReceiptInventoryService::class);
        $this->app->singleton(SalesOrderAmendmentService::class);
        $this->app->singleton(EInvoiceSubmissionService::class);
        $this->app->singleton(BankReconciliationService::class);
        $this->app->singleton(BankStatementCsvImporter::class);
        $this->app->singleton(CustomerReturnReceiptService::class);
        $this->app->singleton(ReturnOrderService::class);
        $this->app->singleton(SupplierReturnService::class);
        $this->app->singleton(SupplierReturnShipmentService::class);
        $this->app->singleton(PriceResolverService::class);
    }

    /**
     * @return list<class-string<\Illuminate\Database\Eloquent\Model>>
     */
    private function policyModels(): array
    {
        return [
            DeliveryNote::class,
            DocumentSequence::class,
            FiscalPeriod::class,
            FiscalYear::class,
            Invoice::class,
            JournalEntry::class,
            Quotation::class,
            SalesOrder::class,
        ];
    }
}
