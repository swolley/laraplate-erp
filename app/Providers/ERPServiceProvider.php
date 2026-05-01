<?php

declare(strict_types=1);

namespace Modules\ERP\Providers;

use Modules\ERP\Contracts\ChartOfAccountsProvider;
use Modules\ERP\Contracts\CurrencyConverter;
use Modules\ERP\Services\Accounting\ChartOfAccountsInstaller;
use Modules\ERP\Services\Accounting\DocumentNumberAllocator;
use Modules\ERP\Services\Accounting\FiscalCalendarInstaller;
use Modules\ERP\Services\Accounting\FiscalPeriodCloser;
use Modules\ERP\Services\Accounting\ItalianCoaProvider;
use Modules\ERP\Services\Accounting\JournalPostingService;
use Modules\ERP\Services\Currency\NoopCurrencyConverter;
use Modules\ERP\Services\Inventory\DeliveryNoteInventoryService;
use Modules\ERP\Services\Inventory\GoodsReceiptInventoryService;
use Modules\ERP\Services\Inventory\StockMovementService;
use Modules\ERP\Services\Taxation\TaxCodeSupersessionService;
use Modules\ERP\Services\Taxation\TaxLineCalculator;
use Nwidart\Modules\Support\ModuleServiceProvider;
use Override;

class ERPServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'ERP';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'erp';

    #[Override]
    public function register(): void
    {
        parent::register();

        $this->app->singleton(CurrencyConverter::class, NoopCurrencyConverter::class);
        $this->app->singleton(ChartOfAccountsProvider::class, ItalianCoaProvider::class);
        $this->app->singleton(ChartOfAccountsInstaller::class, fn ($app): ChartOfAccountsInstaller => new ChartOfAccountsInstaller(
            $app->make(ChartOfAccountsProvider::class),
        ));
        $this->app->singleton(FiscalCalendarInstaller::class);
        $this->app->singleton(FiscalPeriodCloser::class);
        $this->app->singleton(DocumentNumberAllocator::class);
        $this->app->singleton(JournalPostingService::class);
        $this->app->singleton(TaxLineCalculator::class);
        $this->app->singleton(TaxCodeSupersessionService::class);
        $this->app->singleton(StockMovementService::class);
        $this->app->singleton(DeliveryNoteInventoryService::class);
        $this->app->singleton(GoodsReceiptInventoryService::class);
    }
}
