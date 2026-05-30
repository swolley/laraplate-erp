<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\DocumentType;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DocumentSequence;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Models\TaxCode;
use Modules\ERP\Services\Accounting\CreditNoteService;

uses(RefreshDatabase::class);

function createPostedSaleInvoice(array $lines = []): array
{
    $company = Company::query()->create([
        'slug' => 'cn-test-' . uniqid(),
        'name' => 'CN Test Company',
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

    if (empty($lines)) {
        $lines = [
            ['line_no' => 1, 'description' => 'Widget', 'quantity' => 10, 'unit_price' => 100, 'tax_code_id' => $vat->id],
        ];
    }

    foreach ($lines as $line) {
        $invoice->lines()->create($line);
    }

    $invoice->update(['posted_at' => now()]);
    $invoice->refresh();

    return [$company, $invoice, $vat];
}

it('creates credit note from posted invoice', function (): void {
    [$company, $invoice] = createPostedSaleInvoice();

    $service = app(CreditNoteService::class);
    $credit_note = $service->createFromInvoice($invoice);

    expect($credit_note->invoice_type)->toBe(InvoiceType::CreditNote)
        ->and((int) $credit_note->credited_invoice_id)->toBe((int) $invoice->id)
        ->and($credit_note->direction)->toBe($invoice->direction)
        ->and($credit_note->currency)->toBe($invoice->currency)
        ->and((int) $credit_note->company_id)->toBe((int) $company->id);

    $cn_lines = $credit_note->lines()->orderBy('line_no')->get();
    $original_lines = $invoice->lines()->orderBy('line_no')->get();

    expect($cn_lines)->toHaveCount($original_lines->count());

    foreach ($cn_lines as $i => $cn_line) {
        expect((string) $cn_line->quantity)->toBe((string) $original_lines[$i]->quantity)
            ->and((string) $cn_line->unit_price)->toBe((string) $original_lines[$i]->unit_price)
            ->and($cn_line->description)->toBe($original_lines[$i]->description);
    }
});

it('prevents credit note from unposted invoice', function (): void {
    $company = Company::query()->create([
        'slug' => 'cn-unposted',
        'name' => 'CN Unposted',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice->value,
        'currency' => 'EUR',
    ]);

    $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Widget',
        'quantity' => 5,
        'unit_price' => 50,
    ]);

    $service = app(CreditNoteService::class);
    $service->createFromInvoice($invoice);
})->throws(ValidationException::class);

it('prevents credit note from another credit note', function (): void {
    [$company, $invoice] = createPostedSaleInvoice();

    $service = app(CreditNoteService::class);
    $credit_note = $service->createFromInvoice($invoice);

    $service->createFromInvoice($credit_note);
})->throws(ValidationException::class);

it('creates inverted journal entries when posting credit note', function (): void {
    [$company, $invoice, $vat] = createPostedSaleInvoice();

    $original_journal = JournalEntry::query()
        ->withoutGlobalScopes()
        ->findOrFail((int) $invoice->journal_entry_id);
    $original_journal_lines = $original_journal->lines;

    $service = app(CreditNoteService::class);
    $credit_note = $service->createFromInvoice($invoice);
    $credit_note->update(['posted_at' => now()]);
    $credit_note->refresh();

    expect($credit_note->journal_entry_id)->not->toBeNull()
        ->and($credit_note->reference)->not->toBeNull();

    $cn_journal = JournalEntry::query()
        ->withoutGlobalScopes()
        ->findOrFail((int) $credit_note->journal_entry_id);
    $cn_journal_lines = $cn_journal->lines;

    expect($cn_journal_lines)->toHaveCount($original_journal_lines->count());

    foreach ($cn_journal_lines as $i => $cn_jl) {
        $original_jl = $original_journal_lines[$i];
        expect((float) $cn_jl->amount_doc)->toBe(-1.0 * (float) $original_jl->amount_doc);
    }
});

it('prevents credit note total from exceeding original invoice total', function (): void {
    [$company, $invoice] = createPostedSaleInvoice([
        ['line_no' => 1, 'description' => 'Widget', 'quantity' => 10, 'unit_price' => 100],
    ]);

    $service = app(CreditNoteService::class);

    $cn1 = $service->createFromInvoice($invoice, null);
    $cn1->lines()->forceDelete();
    $cn1->lines()->create([
        'line_no' => 1,
        'description' => 'Partial credit',
        'quantity' => 6,
        'unit_price' => 100,
    ]);
    $cn1->update(['posted_at' => now()]);

    $cn2 = $service->createFromInvoice($invoice);
    $cn2->lines()->forceDelete();
    $cn2->lines()->create([
        'line_no' => 1,
        'description' => 'Exceeding credit',
        'quantity' => 6,
        'unit_price' => 100,
    ]);

    $cn2->update(['posted_at' => now()]);
})->throws(ValidationException::class);

it('uses separate document type numbering for credit notes', function (): void {
    [$company, $invoice] = createPostedSaleInvoice();

    $service = app(CreditNoteService::class);
    $credit_note = $service->createFromInvoice($invoice);
    $credit_note->update(['posted_at' => now()]);
    $credit_note->refresh();

    expect($credit_note->reference)->not->toBeNull()
        ->and(DocumentSequence::query()
            ->where('company_id', $company->id)
            ->where('document_type', DocumentType::SalesCreditNote)
            ->exists())->toBeTrue()
        ->and(DocumentSequence::query()
            ->where('company_id', $company->id)
            ->where('document_type', DocumentType::SalesInvoice)
            ->exists())->toBeTrue();
});
