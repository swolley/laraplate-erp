<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\ERP\Casts\DocumentType;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DocumentSequence;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Services\Accounting\DocumentNumberFormatter;
use Symfony\Component\Console\Command\Command;

uses(RefreshDatabase::class);

it('discovers the ERP document sequence audit command', function (): void {
    expect(Artisan::all())->toHaveKey('erp:sequences:audit');
});

it('returns failure when a sequence counter is behind persisted documents', function (): void {
    $company = Company::getDefault() ?? Company::query()->withoutGlobalScopes()->create([
        'slug' => 'sequence-command-audit',
        'name' => 'Sequence command audit',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
        'is_default' => true,
    ]);

    $sequence = DocumentSequence::query()->withoutGlobalScopes()->create([
        'company_id' => $company->getKey(),
        'document_type' => DocumentType::SalesInvoice,
        'fiscal_year' => now()->year,
        'last_number' => 1,
        'gap_allowed' => false,
        'prefix' => 'INV-',
        'padding' => 4,
        'suffix' => '',
    ]);
    $invoice = new Invoice([
        'company_id' => $company->getKey(),
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice,
        'reference' => DocumentNumberFormatter::format($sequence, now()->year, 2),
        'currency' => 'EUR',
        'posted_at' => now(),
    ]);
    $invoice->setSkipValidation(true);
    $invoice->save();

    $this->artisan('erp:sequences:audit', [
        '--company' => $company->getKey(),
        '--year' => now()->year,
        '--format' => 'json',
    ])
        ->expectsOutputToContain('counter_behind_documents')
        ->assertExitCode(Command::FAILURE);
});
