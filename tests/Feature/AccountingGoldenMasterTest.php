<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Casts\DocumentType;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Casts\PaymentScheduleStatus;
use Modules\ERP\Casts\VatRegisterType;
use Modules\ERP\Casts\VatSettlementStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DocumentSequence;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\PaymentScheduleLine;
use Modules\ERP\Models\TaxCode;
use Modules\ERP\Models\VatRegisterEntry;
use Modules\ERP\Services\Accounting\CreditNoteService;
use Modules\ERP\Services\Accounting\VatSettlementService;
use Modules\ERP\Services\Reporting\BalanceSheetService;
use Modules\ERP\Services\Reporting\IncomeStatementService;
use Modules\ERP\Services\Reporting\TrialBalanceService;

uses(RefreshDatabase::class);

function goldenMasterCompany(string $slug): array
{
    $company = Company::query()->create([
        'slug' => $slug . '-' . uniqid(),
        'name' => 'Golden Master ' . $slug,
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $fiscal_year = FiscalYear::query()->create([
        'company_id' => $company->id,
        'year' => 2026,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $period = FiscalPeriod::query()->create([
        'fiscal_year_id' => $fiscal_year->id,
        'period_no' => 1,
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
    ]);

    return [$company, $fiscal_year, $period];
}

function goldenMasterVat(Company $company): TaxCode
{
    return TaxCode::query()->create([
        'company_id' => $company->id,
        'code' => 'VAT22',
        'kind' => 'vat',
        'country' => 'IT',
        'rate' => '22.0000',
        'label' => 'IVA 22%',
        'is_active' => true,
        'effective_from' => '2026-01-01',
    ]);
}

function goldenMasterParty(Company $company, string $name, bool $is_customer = true, bool $is_supplier = false): Party
{
    return Party::query()->create([
        'company_id' => $company->id,
        'name' => $name,
        'is_customer' => $is_customer,
        'is_supplier' => $is_supplier,
    ]);
}

function goldenMasterPostedInvoiceJournalLines(Invoice $invoice): array
{
    $journal = JournalEntry::query()
        ->withoutGlobalScopes()
        ->with('lines')
        ->findOrFail((int) $invoice->journal_entry_id);

    return $journal->lines
        ->mapWithKeys(static fn ($line): array => [
            (string) $line->description => number_format((float) $line->amount_local, 4, '.', ''),
        ])
        ->all();
}

it('posts sale invoice into journal, VAT register, payment schedule, and trial balance', function (): void {
    [$company] = goldenMasterCompany('sale');
    $vat = goldenMasterVat($company);
    goldenMasterParty($company, 'Golden Customer');

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice,
        'currency' => 'EUR',
    ]);

    $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Consulting services',
        'quantity' => '2.0000',
        'unit_price' => '500.0000',
        'tax_code_id' => $vat->id,
    ]);

    $invoice->update(['posted_at' => CarbonImmutable::parse('2026-01-15 10:00:00')]);
    $invoice->refresh();

    expect($invoice->reference)->not->toBeNull()
        ->and($invoice->journal_entry_id)->not->toBeNull();

    expect(goldenMasterPostedInvoiceJournalLines($invoice))->toBe([
        'Trade receivable' => '1220.0000',
        'Sales revenue' => '-1000.0000',
        'VAT output' => '-220.0000',
    ]);

    $vat_entry = VatRegisterEntry::query()
        ->where('invoice_id', (int) $invoice->id)
        ->firstOrFail();

    expect($vat_entry->register_type)->toBe(VatRegisterType::Sales)
        ->and((string) $vat_entry->taxable_amount)->toBe('1000.0000')
        ->and((string) $vat_entry->tax_amount)->toBe('220.0000')
        ->and($vat_entry->protocol_number)->toBe(1);

    $schedule = PaymentScheduleLine::query()
        ->where('invoice_id', (int) $invoice->id)
        ->firstOrFail();

    expect((string) $schedule->amount_local)->toBe('1220.0000')
        ->and($schedule->status)->toBe(PaymentScheduleStatus::Open);
});

it('posts purchase invoice into expense, VAT input, payable, and purchase VAT register', function (): void {
    [$company] = goldenMasterCompany('purchase');
    $vat = goldenMasterVat($company);
    goldenMasterParty($company, 'Golden Supplier', is_customer: false, is_supplier: true);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Purchase,
        'invoice_type' => InvoiceType::Invoice,
        'currency' => 'EUR',
    ]);

    $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Supplier services',
        'quantity' => '1.0000',
        'unit_price' => '300.0000',
        'tax_code_id' => $vat->id,
    ]);

    $invoice->update(['posted_at' => CarbonImmutable::parse('2026-01-16 10:00:00')]);
    $invoice->refresh();

    expect(goldenMasterPostedInvoiceJournalLines($invoice))->toBe([
        'Purchase expense' => '300.0000',
        'VAT input' => '66.0000',
        'Trade payable' => '-366.0000',
    ]);

    $vat_entry = VatRegisterEntry::query()
        ->where('invoice_id', (int) $invoice->id)
        ->firstOrFail();

    expect($vat_entry->register_type)->toBe(VatRegisterType::Purchases)
        ->and((string) $vat_entry->taxable_amount)->toBe('300.0000')
        ->and((string) $vat_entry->tax_amount)->toBe('66.0000');
});

it('posts sale credit note as negative journal and negative VAT register entry', function (): void {
    [$company] = goldenMasterCompany('credit-note');
    $vat = goldenMasterVat($company);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice,
        'currency' => 'EUR',
    ]);
    $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Returned service',
        'quantity' => '1.0000',
        'unit_price' => '100.0000',
        'tax_code_id' => $vat->id,
    ]);
    $invoice->update(['posted_at' => CarbonImmutable::parse('2026-01-17 10:00:00')]);
    $invoice->refresh();

    $credit_note = app(CreditNoteService::class)->createFromInvoice($invoice);
    $credit_note->update(['posted_at' => CarbonImmutable::parse('2026-01-18 10:00:00')]);
    $credit_note->refresh();

    expect($credit_note->invoice_type)->toBe(InvoiceType::CreditNote)
        ->and($credit_note->reference)->not->toBeNull()
        ->and(DocumentSequence::query()
            ->where('company_id', (int) $company->id)
            ->where('document_type', DocumentType::SalesCreditNote)
            ->exists())->toBeTrue();

    expect(goldenMasterPostedInvoiceJournalLines($credit_note))->toBe([
        'Trade receivable' => '-122.0000',
        'Sales revenue' => '100.0000',
        'VAT output' => '22.0000',
    ]);

    $vat_entry = VatRegisterEntry::query()
        ->where('invoice_id', (int) $credit_note->id)
        ->firstOrFail();

    expect($vat_entry->register_type)->toBe(VatRegisterType::Sales)
        ->and((string) $vat_entry->taxable_amount)->toBe('-100.0000')
        ->and((string) $vat_entry->tax_amount)->toBe('-22.0000');
});

it('computes period VAT settlement from posted sale purchase and credit note registers', function (): void {
    [$company, $fiscal_year, $period] = goldenMasterCompany('vat-settlement');
    $vat = goldenMasterVat($company);

    $sale = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice,
        'currency' => 'EUR',
    ]);
    $sale->lines()->create([
        'line_no' => 1,
        'description' => 'Sale',
        'quantity' => '1.0000',
        'unit_price' => '1000.0000',
        'tax_code_id' => $vat->id,
    ]);
    $sale->update(['posted_at' => CarbonImmutable::parse('2026-01-10 10:00:00')]);

    $purchase = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Purchase,
        'invoice_type' => InvoiceType::Invoice,
        'currency' => 'EUR',
    ]);
    $purchase->lines()->create([
        'line_no' => 1,
        'description' => 'Purchase',
        'quantity' => '1.0000',
        'unit_price' => '300.0000',
        'tax_code_id' => $vat->id,
    ]);
    $purchase->update(['posted_at' => CarbonImmutable::parse('2026-01-11 10:00:00')]);

    $credit_note = app(CreditNoteService::class)->createFromInvoice($sale->fresh());
    $credit_note->lines()->forceDelete();
    $credit_note->lines()->create([
        'line_no' => 1,
        'description' => 'Partial credit',
        'quantity' => '1.0000',
        'unit_price' => '100.0000',
        'tax_code_id' => $vat->id,
    ]);
    $credit_note->update(['posted_at' => CarbonImmutable::parse('2026-01-12 10:00:00')]);

    $settlement = app(VatSettlementService::class)->compute((int) $company->id, (int) $period->id);

    expect($settlement->status)->toBe(VatSettlementStatus::Draft)
        ->and((string) $settlement->vat_sales)->toBe('198.0000')
        ->and((string) $settlement->vat_purchases)->toBe('66.0000')
        ->and((string) $settlement->previous_credit)->toBe('0.0000')
        ->and((string) $settlement->settlement_amount)->toBe('132.0000');
});

it('rolls posted documents into trial balance income statement and balance sheet', function (): void {
    [$company] = goldenMasterCompany('statements');
    $vat = goldenMasterVat($company);

    $sale = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice,
        'currency' => 'EUR',
    ]);
    $sale->lines()->create([
        'line_no' => 1,
        'description' => 'Sale',
        'quantity' => '1.0000',
        'unit_price' => '1000.0000',
        'tax_code_id' => $vat->id,
    ]);
    $sale->update(['posted_at' => CarbonImmutable::parse('2026-01-20 10:00:00')]);

    $purchase = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Purchase,
        'invoice_type' => InvoiceType::Invoice,
        'currency' => 'EUR',
    ]);
    $purchase->lines()->create([
        'line_no' => 1,
        'description' => 'Purchase',
        'quantity' => '1.0000',
        'unit_price' => '300.0000',
        'tax_code_id' => $vat->id,
    ]);
    $purchase->update(['posted_at' => CarbonImmutable::parse('2026-01-21 10:00:00')]);

    $trial_balance = app(TrialBalanceService::class)->generate(
        (int) $company->id,
        CarbonImmutable::parse('2026-01-31 23:59:59'),
    );
    $income_statement = app(IncomeStatementService::class)->generate(
        (int) $company->id,
        CarbonImmutable::parse('2026-01-01'),
        CarbonImmutable::parse('2026-01-31 23:59:59'),
    );
    $balance_sheet = app(BalanceSheetService::class)->generate(
        (int) $company->id,
        CarbonImmutable::parse('2026-01-31 23:59:59'),
    );

    $debit_total = collect($trial_balance)->sum(static fn (array $row): float => (float) $row['debit']);
    $credit_total = collect($trial_balance)->sum(static fn (array $row): float => (float) $row['credit']);

    expect(number_format($debit_total, 4, '.', ''))->toBe(number_format($credit_total, 4, '.', ''))
        ->and($income_statement['total_revenue'])->toBe('1000.0000')
        ->and($income_statement['total_expenses'])->toBe('300.0000')
        ->and($income_statement['net_income'])->toBe('700.0000')
        ->and($balance_sheet['is_balanced'])->toBeTrue();
});

it('unposts invoice by reversing journal clearing VAT register and removing schedule', function (): void {
    [$company] = goldenMasterCompany('unpost');
    $vat = goldenMasterVat($company);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice,
        'currency' => 'EUR',
    ]);
    $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Sale',
        'quantity' => '1.0000',
        'unit_price' => '100.0000',
        'tax_code_id' => $vat->id,
    ]);
    $invoice->update(['posted_at' => CarbonImmutable::parse('2026-01-22 10:00:00')]);
    $invoice->refresh();

    $original_journal_id = (int) $invoice->journal_entry_id;

    $invoice->update(['posted_at' => null]);
    $invoice->refresh();

    expect($invoice->journal_entry_id)->toBeNull()
        ->and($invoice->reference)->toBeNull()
        ->and(VatRegisterEntry::query()->where('invoice_id', (int) $invoice->id)->count())->toBe(0)
        ->and(PaymentScheduleLine::query()->where('invoice_id', (int) $invoice->id)->count())->toBe(0)
        ->and(JournalEntry::query()
            ->withoutGlobalScopes()
            ->where('reverses_journal_entry_id', $original_journal_id)
            ->exists())->toBeTrue();
});
