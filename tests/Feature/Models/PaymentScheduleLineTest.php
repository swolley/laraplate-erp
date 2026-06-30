<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\Payment;
use Modules\ERP\Models\PaymentScheduleLine;

it('defines invoice and payments relationships', function (): void {
    $line = new PaymentScheduleLine;

    expect($line->invoice())->toBeInstanceOf(BelongsTo::class)
        ->and($line->payments())->toBeInstanceOf(BelongsToMany::class)
        ->and($line->invoice()->getRelated())->toBeInstanceOf(Invoice::class)
        ->and($line->payments()->getRelated())->toBeInstanceOf(Payment::class);
});
