<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\ERP\Models\BankStatement;
use Modules\ERP\Models\BankStatementLine;
use Modules\ERP\Models\Payment;

it('defines bank statement and matched payment relationships', function (): void {
    $line = new BankStatementLine;

    expect($line->bank_statement())->toBeInstanceOf(BelongsTo::class)
        ->and($line->matched_payment())->toBeInstanceOf(BelongsTo::class)
        ->and($line->bank_statement()->getRelated())->toBeInstanceOf(BankStatement::class)
        ->and($line->matched_payment()->getRelated())->toBeInstanceOf(Payment::class);
});
