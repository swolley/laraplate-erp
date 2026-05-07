<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Casts\VatRegisterType;
use Modules\ERP\Casts\VatSettlementStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\TaxCode;
use Modules\ERP\Models\VatRegisterEntry;
use Modules\ERP\Models\VatSettlement;
use Modules\ERP\Services\Accounting\VatRegisterService;
use Modules\ERP\Services\Accounting\VatSettlementService;

uses(RefreshDatabase::class);

function createVatTestCompanyWithFiscalYear(): array
{
    $company = Company::query()->create([
        'slug' => 'vat-test-' . uniqid(),
        'name' => 'VAT Test Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $fiscal_year = FiscalYear::query()->create([
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

    return [$company, $fiscal_year, $vat];
}

function createPostedInvoice(
    Company $company,
    TaxCode $vat,
    InvoiceDirection $direction = InvoiceDirection::Sale,
    InvoiceType $invoice_type = InvoiceType::Invoice,
    float $quantity = 1,
    float $unit_price = 100,
): Invoice {
    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => $direction,
        'invoice_type' => $invoice_type,
        'currency' => 'EUR',
        'posted_at' => now(),
    ]);

    $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Test item',
        'quantity' => $quantity,
        'unit_price' => $unit_price,
        'tax_code_id' => $vat->id,
        'tax_code' => $vat->code,
        'tax_rate' => (string) $vat->rate,
        'tax_label' => $vat->label,
    ]);

    return $invoice;
}

it('registers invoice in VAT sales register with protocol number', function (): void {
    [$company, $fiscal_year, $vat] = createVatTestCompanyWithFiscalYear();
    $invoice = createPostedInvoice($company, $vat);

    app(VatRegisterService::class)->register($invoice);

    $entries = VatRegisterEntry::query()->withoutGlobalScopes()->where('invoice_id', $invoice->id)->get();

    expect($entries)->toHaveCount(1);

    $entry = $entries->first();
    expect($entry->register_type)->toBe(VatRegisterType::Sales)
        ->and($entry->protocol_number)->toBe(1)
        ->and((float) $entry->taxable_amount)->toBe(100.0)
        ->and((float) $entry->tax_amount)->toBe(22.0)
        ->and((int) $entry->fiscal_year_id)->toBe((int) $fiscal_year->id);
});

it('registers purchase invoice in VAT purchases register', function (): void {
    [$company, $fiscal_year, $vat] = createVatTestCompanyWithFiscalYear();
    $invoice = createPostedInvoice($company, $vat, InvoiceDirection::Purchase);

    app(VatRegisterService::class)->register($invoice);

    $entry = VatRegisterEntry::query()->withoutGlobalScopes()->where('invoice_id', $invoice->id)->firstOrFail();

    expect($entry->register_type)->toBe(VatRegisterType::Purchases);
});

it('assigns sequential protocol numbers per register and fiscal year', function (): void {
    [$company, $fiscal_year, $vat] = createVatTestCompanyWithFiscalYear();
    $service = app(VatRegisterService::class);

    $protocols = [];
    for ($i = 0; $i < 3; $i++) {
        $invoice = createPostedInvoice($company, $vat);
        $service->register($invoice);

        $entry = VatRegisterEntry::query()
            ->withoutGlobalScopes()
            ->where('invoice_id', $invoice->id)
            ->firstOrFail();
        $protocols[] = $entry->protocol_number;
    }

    expect($protocols)->toBe([1, 2, 3]);
});

it('removes entries on unregister', function (): void {
    [$company, $fiscal_year, $vat] = createVatTestCompanyWithFiscalYear();
    $invoice = createPostedInvoice($company, $vat);
    $service = app(VatRegisterService::class);

    $service->register($invoice);
    expect(VatRegisterEntry::query()->withoutGlobalScopes()->where('invoice_id', $invoice->id)->count())->toBe(1);

    $service->unregister($invoice);
    expect(VatRegisterEntry::query()->withoutGlobalScopes()->where('invoice_id', $invoice->id)->count())->toBe(0);
});

it('creates negative register entry for credit note', function (): void {
    [$company, $fiscal_year, $vat] = createVatTestCompanyWithFiscalYear();
    $invoice = createPostedInvoice($company, $vat, InvoiceDirection::Sale, InvoiceType::CreditNote, 1, 100);

    app(VatRegisterService::class)->register($invoice);

    $entry = VatRegisterEntry::query()->withoutGlobalScopes()->where('invoice_id', $invoice->id)->firstOrFail();

    expect((float) $entry->taxable_amount)->toBeLessThan(0)
        ->and((float) $entry->tax_amount)->toBeLessThan(0)
        ->and((float) $entry->taxable_amount)->toBe(-100.0)
        ->and((float) $entry->tax_amount)->toBe(-22.0);
});

it('computes VAT settlement correctly', function (): void {
    [$company, $fiscal_year, $vat] = createVatTestCompanyWithFiscalYear();

    $period = FiscalPeriod::query()->create([
        'fiscal_year_id' => $fiscal_year->id,
        'period_no' => 1,
        'start_date' => now()->startOfYear()->toDateString(),
        'end_date' => now()->startOfYear()->addMonth()->subDay()->toDateString(),
    ]);

    VatRegisterEntry::query()->create([
        'company_id' => $company->id,
        'invoice_id' => createPostedInvoice($company, $vat)->id,
        'register_type' => VatRegisterType::Sales->value,
        'protocol_number' => 1,
        'registration_date' => now()->startOfYear()->addDays(5)->toDateString(),
        'fiscal_year_id' => $fiscal_year->id,
        'tax_code_id' => $vat->id,
        'taxable_amount' => '1000.0000',
        'tax_amount' => '220.0000',
    ]);

    VatRegisterEntry::query()->create([
        'company_id' => $company->id,
        'invoice_id' => createPostedInvoice($company, $vat, InvoiceDirection::Purchase)->id,
        'register_type' => VatRegisterType::Purchases->value,
        'protocol_number' => 1,
        'registration_date' => now()->startOfYear()->addDays(10)->toDateString(),
        'fiscal_year_id' => $fiscal_year->id,
        'tax_code_id' => $vat->id,
        'taxable_amount' => '500.0000',
        'tax_amount' => '110.0000',
    ]);

    $settlement = app(VatSettlementService::class)->compute((int) $company->id, (int) $period->id);

    expect((float) $settlement->vat_sales)->toBe(220.0)
        ->and((float) $settlement->vat_purchases)->toBe(110.0)
        ->and((float) $settlement->previous_credit)->toBe(0.0)
        ->and((float) $settlement->settlement_amount)->toBe(110.0)
        ->and($settlement->status)->toBe(VatSettlementStatus::Draft);
});

it('carries forward previous credit', function (): void {
    [$company, $fiscal_year, $vat] = createVatTestCompanyWithFiscalYear();

    $period_m1 = FiscalPeriod::query()->create([
        'fiscal_year_id' => $fiscal_year->id,
        'period_no' => 1,
        'start_date' => now()->startOfYear()->toDateString(),
        'end_date' => now()->startOfYear()->addMonth()->subDay()->toDateString(),
    ]);

    $period_m2 = FiscalPeriod::query()->create([
        'fiscal_year_id' => $fiscal_year->id,
        'period_no' => 2,
        'start_date' => now()->startOfYear()->addMonth()->toDateString(),
        'end_date' => now()->startOfYear()->addMonths(2)->subDay()->toDateString(),
    ]);

    VatSettlement::query()->create([
        'company_id' => $company->id,
        'fiscal_period_id' => $period_m1->id,
        'vat_sales' => '100.0000',
        'vat_purchases' => '300.0000',
        'previous_credit' => '0.0000',
        'settlement_amount' => '-200.0000',
        'status' => VatSettlementStatus::Confirmed->value,
        'confirmed_at' => now(),
        'confirmed_by' => 1,
    ]);

    VatRegisterEntry::query()->create([
        'company_id' => $company->id,
        'invoice_id' => createPostedInvoice($company, $vat)->id,
        'register_type' => VatRegisterType::Sales->value,
        'protocol_number' => 1,
        'registration_date' => now()->startOfYear()->addMonth()->addDays(5)->toDateString(),
        'fiscal_year_id' => $fiscal_year->id,
        'tax_code_id' => $vat->id,
        'taxable_amount' => '1000.0000',
        'tax_amount' => '220.0000',
    ]);

    $settlement = app(VatSettlementService::class)->compute((int) $company->id, (int) $period_m2->id);

    expect((float) $settlement->previous_credit)->toBe(200.0)
        ->and((float) $settlement->settlement_amount)->toBe(20.0);
});
