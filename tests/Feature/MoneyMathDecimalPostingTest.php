<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Models\TaxCode;

uses(RefreshDatabase::class);

it('posts a fractional sale invoice with decimal-exact, balanced journal totals', function (): void {
    $company = Company::query()->create([
        'slug' => 'money-frac',
        'name' => 'Money Frac',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    FiscalYear::query()->create([
        'company_id' => $company->id,
        'year' => (int) now()->format('Y'),
        'start_date' => now()->startOfYear()->toDateString(),
        'end_date' => now()->endOfYear()->toDateString(),
    ]);
    $vat = TaxCode::query()->create([
        'company_id' => $company->id,
        'code' => 'VAT22',
        'kind' => 'vat',
        'country' => 'IT',
        'rate' => 22,
        'label' => 'IVA 22%',
        'is_active' => true,
        'effective_from' => now()->toDateString(),
    ]);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice->value,
        'currency' => 'EUR',
    ]);
    // net 0.9999 -> tax 0.2200
    $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Frac A',
        'quantity' => 3,
        'unit_price' => '0.3333',
        'tax_code_id' => $vat->id,
    ]);
    // net 7.7777 -> tax 1.7111
    $invoice->lines()->create([
        'line_no' => 2,
        'description' => 'Frac B',
        'quantity' => 7,
        'unit_price' => '1.1111',
        'tax_code_id' => $vat->id,
    ]);

    $invoice->update(['posted_at' => now()]);

    $journal = JournalEntry::query()->withoutGlobalScopes()
        ->findOrFail((int) $invoice->fresh()->journal_entry_id);
    $amounts = $journal->lines->pluck('amount_doc')->map(fn ($v): string => (string) $v)->all();

    // receivable +gross 10.7087, revenue -net 8.7776, vat_output -tax 1.9311
    expect($amounts)->toContain('10.7087')
        ->and($amounts)->toContain('-8.7776')
        ->and($amounts)->toContain('-1.9311');

    // Double-entry balance is exactly zero.
    $sum = array_reduce(
        $amounts,
        fn (Brick\Math\BigDecimal $carry, string $v): Brick\Math\BigDecimal => $carry->plus(Brick\Math\BigDecimal::of($v)),
        Brick\Math\BigDecimal::zero(),
    );
    expect($sum->toScale(4)->__toString())->toBe('0.0000');
});
