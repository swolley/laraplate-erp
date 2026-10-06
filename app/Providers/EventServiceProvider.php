<?php

declare(strict_types=1);

namespace Modules\ERP\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\ERP\Events\SalesOrderConfirmed;
use Modules\ERP\Listeners\ReserveStockForConfirmedSalesOrder;
use Override;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    #[Override]
    protected $listen = [
        SalesOrderConfirmed::class => [
            ReserveStockForConfirmedSalesOrder::class,
        ],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    #[Override]
    protected static $shouldDiscoverEvents = true;
}
