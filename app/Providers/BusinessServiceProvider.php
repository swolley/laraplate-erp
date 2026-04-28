<?php

declare(strict_types=1);

namespace Modules\Business\Providers;

use Modules\Business\Contracts\CurrencyConverter;
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
    }
}
