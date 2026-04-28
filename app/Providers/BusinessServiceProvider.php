<?php

declare(strict_types=1);

namespace Modules\Business\Providers;

use Modules\Business\Contracts\ChartOfAccountsProvider;
use Modules\Business\Contracts\CurrencyConverter;
use Modules\Business\Services\Accounting\ChartOfAccountsInstaller;
use Modules\Business\Services\Accounting\DocumentNumberAllocator;
use Modules\Business\Services\Accounting\FiscalCalendarInstaller;
use Modules\Business\Services\Accounting\FiscalPeriodCloser;
use Modules\Business\Services\Accounting\ItalianCoaProvider;
use Modules\Business\Services\Accounting\JournalPostingService;
use Modules\Business\Services\Currency\NoopCurrencyConverter;
use Nwidart\Modules\Support\ModuleServiceProvider;
use Override;

class BusinessServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Business';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'business';

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
    }
}
