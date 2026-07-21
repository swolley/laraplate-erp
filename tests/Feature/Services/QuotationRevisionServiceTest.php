<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\BillingMode;
use Modules\ERP\Casts\QuoteStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\Quotation;
use Modules\ERP\Services\Quotations\QuotationRevisionService;

uses(RefreshDatabase::class);

function revisionSourceQuotation(): Quotation
{
    $company = Company::query()->create([
        'slug' => 'quote-revision-' . uniqid(),
        'name' => 'Quote revision',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Revision customer',
        'is_customer' => true,
    ]);
    $quotation = Quotation::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'currency' => 'EUR',
        'notes' => 'Original commercial conditions',
        'status' => QuoteStatus::Sent,
        'version' => 0,
    ]);
    $quotation->quotation_items()->create([
        'name' => 'Consulting',
        'billing_mode' => BillingMode::Unit,
        'quantity' => '2.0000',
        'unit_price' => '75.0000',
    ]);

    return $quotation;
}

it('creates a draft revision snapshot with a linear parent link', function (): void {
    $source = revisionSourceQuotation();

    $revision = app(QuotationRevisionService::class)->createRevision($source);

    expect($revision->revises_quotation_id)->toBe($source->id)
        ->and($revision->version)->toBe(1)
        ->and($revision->status)->toBe(QuoteStatus::Draft)
        ->and($revision->party_id)->toBe($source->party_id)
        ->and($revision->notes)->toBe($source->notes)
        ->and($revision->quotation_items)->toHaveCount(1)
        ->and($revision->quotation_items->first()->name)->toBe('Consulting')
        ->and((string) $revision->quotation_items->first()->unit_price)->toBe('75.0000')
        ->and($source->fresh()->revision->is($revision))->toBeTrue();
});

it('keeps the revision chain linear and requires the latest non-draft source', function (): void {
    $source = revisionSourceQuotation();
    $service = app(QuotationRevisionService::class);
    $revision = $service->createRevision($source);

    expect(fn () => $service->createRevision($source->fresh()))
        ->toThrow(ValidationException::class)
        ->and(fn () => $service->createRevision($revision))
        ->toThrow(ValidationException::class);

    $revision->update(['status' => QuoteStatus::Sent]);
    $next = $service->createRevision($revision->fresh());

    expect($next->version)->toBe(2)
        ->and($next->revises_quotation_id)->toBe($revision->id);
});
