<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\ERP\Models\Payment;
use Modules\ERP\Models\PaymentAllocation;
use Modules\ERP\Models\PaymentScheduleLine;

it('defines payment and schedule line relationships', function (): void {
    $allocation = new PaymentAllocation;

    expect($allocation->payment())->toBeInstanceOf(BelongsTo::class)
        ->and($allocation->schedule_line())->toBeInstanceOf(BelongsTo::class)
        ->and($allocation->payment()->getRelated())->toBeInstanceOf(Payment::class)
        ->and($allocation->schedule_line()->getRelated())->toBeInstanceOf(PaymentScheduleLine::class);
});
