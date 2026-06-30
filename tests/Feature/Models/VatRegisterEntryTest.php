<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\TaxCode;
use Modules\ERP\Models\VatRegisterEntry;

it('defines invoice fiscal year and tax code relationships', function (): void {
    $entry = new VatRegisterEntry;

    expect($entry->invoice())->toBeInstanceOf(BelongsTo::class)
        ->and($entry->fiscal_year())->toBeInstanceOf(BelongsTo::class)
        ->and($entry->tax_code())->toBeInstanceOf(BelongsTo::class)
        ->and($entry->invoice()->getRelated())->toBeInstanceOf(Invoice::class)
        ->and($entry->fiscal_year()->getRelated())->toBeInstanceOf(FiscalYear::class)
        ->and($entry->tax_code()->getRelated())->toBeInstanceOf(TaxCode::class);
});
