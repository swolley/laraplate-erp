<?php

declare(strict_types=1);

namespace Modules\ERP\Events;

use Illuminate\Queue\SerializesModels;
use Modules\ERP\Models\SalesOrder;

/**
 * Dispatched when a sales order transitions into the confirmed state.
 *
 * ERP owns the event but has no knowledge of its consumers: downstream
 * modules (e.g. MES, which auto-creates production orders for manufactured
 * lines) subscribe to it without ERP depending on them.
 */
final class SalesOrderConfirmed
{
    use SerializesModels;

    public function __construct(public readonly SalesOrder $salesOrder) {}
}
