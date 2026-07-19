<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Casts\DocumentType;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DocumentSequence;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Services\Accounting\DocumentNumberFormatter;
use Modules\ERP\Services\Accounting\DocumentSequenceAuditService;

uses(RefreshDatabase::class);

function sequenceAuditCompany(): Company
{
    return Company::getDefault() ?? Company::query()->withoutGlobalScopes()->create([
        'slug' => 'sequence-audit',
        'name' => 'Sequence audit',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
        'is_default' => true,
    ]);
}

function sequenceAuditSequence(Company $company, int $last_number, bool $gap_allowed = false): DocumentSequence
{
    return DocumentSequence::query()->withoutGlobalScopes()->create([
        'company_id' => $company->getKey(),
        'document_type' => DocumentType::SalesInvoice,
        'fiscal_year' => now()->year,
        'last_number' => $last_number,
        'gap_allowed' => $gap_allowed,
        'prefix' => 'INV-',
        'padding' => 4,
        'suffix' => '',
    ]);
}

function sequenceAuditInvoice(Company $company, string $reference): Invoice
{
    $invoice = new Invoice([
        'company_id' => $company->getKey(),
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice,
        'reference' => $reference,
        'currency' => 'EUR',
        'posted_at' => now(),
    ]);
    $invoice->setSkipValidation(true);
    $invoice->save();

    return $invoice;
}

it('accepts a sequence aligned with persisted invoice references', function (): void {
    $company = sequenceAuditCompany();
    $sequence = sequenceAuditSequence($company, 2);
    sequenceAuditInvoice($company, DocumentNumberFormatter::format($sequence, now()->year, 1));
    sequenceAuditInvoice($company, DocumentNumberFormatter::format($sequence, now()->year, 2));

    $result = app(DocumentSequenceAuditService::class)->audit((int) $company->getKey(), now()->year);

    expect($result['summary']['failure'])->toBe(0)
        ->and(collect($result['checks'])->pluck('code'))->toContain('sequence_consistent');
});

it('fails when persisted documents exceed the sequence counter', function (): void {
    $company = sequenceAuditCompany();
    $sequence = sequenceAuditSequence($company, 1);
    sequenceAuditInvoice($company, DocumentNumberFormatter::format($sequence, now()->year, 2));

    $result = app(DocumentSequenceAuditService::class)->audit((int) $company->getKey(), now()->year);

    expect(collect($result['checks'])->pluck('code'))->toContain('counter_behind_documents')
        ->and($result['summary']['failure'])->toBeGreaterThan(0);
});

it('detects duplicate references and gaps without mutating data', function (): void {
    $company = sequenceAuditCompany();
    $sequence = sequenceAuditSequence($company, 3);
    $first = DocumentNumberFormatter::format($sequence, now()->year, 1);
    sequenceAuditInvoice($company, $first);
    sequenceAuditInvoice($company, $first);
    sequenceAuditInvoice($company, DocumentNumberFormatter::format($sequence, now()->year, 3));
    $sequence_before = $sequence->fresh()->getAttributes();

    $result = app(DocumentSequenceAuditService::class)->audit((int) $company->getKey(), now()->year);
    $codes = collect($result['checks'])->pluck('code');

    expect($codes)->toContain('duplicate_references', 'sequence_gaps')
        ->and($sequence->fresh()->getAttributes())->toBe($sequence_before);
});
