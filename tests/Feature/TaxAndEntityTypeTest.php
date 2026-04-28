<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Business\Casts\EntityType;
use Modules\Business\Casts\InvoiceDirection;
use Modules\Business\Casts\TaxKind;
use Modules\Business\Exceptions\TaxCodeImmutableAttributeException;
use Modules\Business\Models\Company;
use Modules\Business\Models\Invoice;
use Modules\Business\Models\InvoiceLine;
use Modules\Business\Models\TaxCode;
use Modules\Business\Services\Taxation\TaxCodeSupersessionService;
use Modules\Business\Services\Taxation\TaxLineCalculator;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('lists Business entity types without movements and with opportunity stages', function (): void {
    $values = EntityType::values();

    expect($values)->toContain('activities')
        ->and($values)->toContain('opportunity_stages')
        ->and($values)->not->toContain('movements');
});

it('computes VAT from net using a TaxCode row', function (): void {
    $company = Company::query()->create([
        'slug' => 'tax-co',
        'name' => 'Tax',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
        'is_default' => false,
    ]);

    $vat = TaxCode::withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'code' => 'IT_VAT_22',
        'kind' => TaxKind::Vat,
        'country' => 'IT',
        'rate' => '22.0000',
        'label' => 'IVA 22%',
        'is_active' => true,
        'effective_from' => '2015-01-01',
    ]);

    $calculator = new TaxLineCalculator;
    $parts = $calculator->computeVatFromNet($vat, '100.0000');

    expect($parts['taxable'])->toBe('100.0000')
        ->and($parts['tax'])->toBe('22.0000')
        ->and($parts['gross'])->toBe('122.0000');
});

it('resolves the active tax code at a posting date', function (): void {
    $company = Company::query()->create([
        'slug' => 'res-co',
        'name' => 'Res',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
        'is_default' => false,
    ]);

    TaxCode::withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'code' => 'IT_VAT_FUTURE',
        'kind' => TaxKind::Vat,
        'country' => 'IT',
        'rate' => '24.0000',
        'label' => 'IVA 24%',
        'is_active' => true,
        'effective_from' => '2027-01-01',
    ]);

    $legacy = TaxCode::withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'code' => 'IT_VAT_LEGACY',
        'kind' => TaxKind::Vat,
        'country' => 'IT',
        'rate' => '22.0000',
        'label' => 'IVA 22%',
        'is_active' => true,
        'effective_from' => '2000-01-01',
    ]);

    $calculator = new TaxLineCalculator;
    $resolved = $calculator->resolveActiveAt($company, 'IT_VAT_LEGACY', new \DateTimeImmutable('2026-06-01'));

    expect($resolved->id)->toBe($legacy->id);
});

it('forbids mutating immutable TaxCode attributes', function (): void {
    $company = Company::query()->create([
        'slug' => 'im-co',
        'name' => 'Im',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
        'is_default' => false,
    ]);

    $row = TaxCode::withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'code' => 'X',
        'kind' => TaxKind::Vat,
        'country' => 'IT',
        'rate' => '5.0000',
        'label' => 'X',
        'is_active' => true,
        'effective_from' => '2000-01-01',
    ]);

    expect(fn () => $row->update(['rate' => '10.0000']))->toThrow(TaxCodeImmutableAttributeException::class);
});

it('keeps invoice line fiscal snapshot after tax code supersession', function (): void {
    $company = Company::query()->create([
        'slug' => 'inv-co',
        'name' => 'Inv',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
        'is_default' => false,
    ]);

    $obsolete = TaxCode::withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'code' => 'IT_VAT_22_A',
        'kind' => TaxKind::Vat,
        'country' => 'IT',
        'rate' => '22.0000',
        'label' => 'IVA 22%',
        'is_active' => true,
        'effective_from' => '2000-01-01',
    ]);

    $calculator = new TaxLineCalculator;
    $snapshot = $calculator->snapshotForLine($obsolete);

    $invoice = Invoice::withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Sale,
        'currency' => 'EUR',
        'posted_at' => now(),
    ]);

    InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'line_no' => 1,
        'quantity' => '1.0000',
        'unit_price' => '100.0000',
        'tax_code_id' => $obsolete->id,
        'tax_code' => $snapshot['tax_code'],
        'tax_rate' => $snapshot['tax_rate'],
        'tax_label' => $snapshot['tax_label'],
    ]);

    $replacement = TaxCode::withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'code' => 'IT_VAT_24_A',
        'kind' => TaxKind::Vat,
        'country' => 'IT',
        'rate' => '24.0000',
        'label' => 'IVA 24%',
        'is_active' => true,
        'effective_from' => '2027-01-01',
    ]);

    resolve(TaxCodeSupersessionService::class)->linkReplacement($obsolete, $replacement);

    $line = InvoiceLine::query()->where('invoice_id', $invoice->id)->firstOrFail();

    expect($line->tax_code)->toBe('IT_VAT_22_A')
        ->and((string) $line->tax_rate)->toBe('22.0000')
        ->and($obsolete->fresh()->is_active)->toBeFalse();
});
