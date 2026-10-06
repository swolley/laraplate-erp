<?php

declare(strict_types=1);

namespace Modules\ERP\Events;

use Illuminate\Queue\SerializesModels;
use Modules\ERP\Models\SalesOrder;

/**
 * Dispatched when a sales order transitions into the cancelled state.
 *
 * ERP owns the event but has no knowledge of its consumers: the stock
 * reservations held for the order's lines are released by a listener
 * subscribed to this event, keeping the model free of inventory concerns.
 */
final class SalesOrderCancelled
{
    use SerializesModels;

    public function __construct(public readonly SalesOrder $salesOrder) {}
}
