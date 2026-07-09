<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Models\Party;

uses(RefreshDatabase::class);

it('exposes credit note and journal entry relationships', function (): void {
    $company = Company::query()->create([
        'slug' => 'invoice-rel-' . uniqid(),
        'name' => 'Invoice Relations Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $original = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice,
        'currency' => 'EUR',
    ]);
    $credit_note = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::CreditNote,
        'credited_invoice_id' => $original->id,
        'currency' => 'EUR',
    ]);
    $journal = JournalEntry::query()->create([
        'company_id' => $company->id,
        'description' => 'Invoice posting',
    ]);
    $original->journal_entry_id = $journal->id;
    $original->save();

    expect((new Invoice)->credit_notes())->toBeInstanceOf(HasMany::class)
        ->and((new Invoice)->journal_entry())->toBeInstanceOf(BelongsTo::class)
        ->and($original->fresh()->credit_notes)->toHaveCount(1)
        ->and($original->fresh()->credit_notes->first()?->id)->toBe($credit_note->id)
        ->and($original->fresh()->journal_entry?->id)->toBe($journal->id);
});

it('rejects credit notes that reference invoices from another company', function (): void {
    $seller = Company::query()->create([
        'slug' => 'invoice-seller-' . uniqid(),
        'name' => 'Seller Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $other_company = Company::query()->create([
        'slug' => 'invoice-other-' . uniqid(),
        'name' => 'Other Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $original = Invoice::query()->create([
        'company_id' => $seller->id,
        'party_id' => Party::query()->create([
            'company_id' => $seller->id,
            'name' => 'Buyer',
        ])->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice,
        'currency' => 'EUR',
    ]);

    $credit_note = new Invoice([
        'company_id' => $other_company->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::CreditNote,
        'credited_invoice_id' => $original->id,
        'currency' => 'EUR',
    ]);

    expect(fn () => $credit_note->save())
        ->toThrow(ValidationException::class, 'same company');
});

it('rejects credit notes that reference a missing invoice', function (): void {
    $company = Company::query()->create([
        'slug' => 'invoice-missing-' . uniqid(),
        'name' => 'Missing Credit Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $credit_note = new Invoice([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::CreditNote,
        'credited_invoice_id' => 999_999,
        'currency' => 'EUR',
    ]);

    expect(fn () => $credit_note->save())
        ->toThrow(ValidationException::class, 'does not exist');
});
