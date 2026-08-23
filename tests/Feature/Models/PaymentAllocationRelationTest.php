<?php

declare(strict_types=1);

use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Models\Payment;
use Modules\ERP\Models\PaymentScheduleLine;

test('the payment-allocation many-to-many relations target the erp_payment_allocations pivot', function (): void {
    // Regression: these relations previously named the pivot 'payment_allocations'
    // (unprefixed), a table that does not exist — the join silently pointed nowhere.
    expect((new Payment)->schedule_lines()->getTable())->toBe(ERPTables::PaymentAllocations->value);
    expect((new PaymentScheduleLine)->payments()->getTable())->toBe(ERPTables::PaymentAllocations->value);
});
