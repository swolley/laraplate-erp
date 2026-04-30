<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\OpportunityStatus;
use Modules\ERP\Casts\QuoteStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Customer;
use Modules\ERP\Models\Opportunity;
use Modules\ERP\Models\Quotation;
use Modules\ERP\Tests\Support\OpportunityStageTaxonomy;

uses(RefreshDatabase::class);

it('rejects a quotation when opportunity belongs to another customer', function (): void {
    $stage_id = OpportunityStageTaxonomy::insertMinimalId('quo-opp-align-stage');

    $company = Company::query()->create([
        'slug' => 'quo-opp-val',
        'name' => 'Quo Opp Val',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $customer_one = Customer::query()->create([
        'company_id' => $company->id,
        'name' => 'One',
    ]);

    $customer_two = Customer::query()->create([
        'company_id' => $company->id,
        'name' => 'Two',
    ]);

    $opportunity = Opportunity::query()->create([
        'company_id' => $company->id,
        'customer_id' => $customer_two->id,
        'stage_taxonomy_id' => $stage_id,
        'name' => 'Deal B',
        'status' => OpportunityStatus::OPEN,
        'expected_currency_doc' => 'EUR',
        'expected_currency_local' => 'EUR',
        'expected_fx_rate' => 1,
    ]);

    try {
        Quotation::query()->create([
            'company_id' => $company->id,
            'customer_id' => $customer_one->id,
            'opportunity_id' => $opportunity->id,
            'currency' => 'EUR',
            'status' => QuoteStatus::DRAFT,
            'version' => 0,
        ]);
        expect(false)->toBeTrue('expected ValidationException was not thrown');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('opportunity_id')
            ->and($exception->errors()['opportunity_id'][0] ?? '')
            ->toContain('same customer');
    }
});

it('marks linked opportunity as won when quotation is accepted', function (): void {
    $stage_id = OpportunityStageTaxonomy::insertMinimalId('quo-opp-won-stage');

    $company = Company::query()->create([
        'slug' => 'quo-opp-win',
        'name' => 'Quo Opp Win',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $customer = Customer::query()->create([
        'company_id' => $company->id,
        'name' => 'Buyer',
    ]);

    $opportunity = Opportunity::query()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'stage_taxonomy_id' => $stage_id,
        'name' => 'Deal Won Path',
        'status' => OpportunityStatus::OPEN,
        'expected_currency_doc' => 'EUR',
        'expected_currency_local' => 'EUR',
        'expected_fx_rate' => 1,
    ]);

    Quotation::query()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'opportunity_id' => $opportunity->id,
        'currency' => 'EUR',
        'status' => QuoteStatus::ACCEPTED,
        'version' => 1,
    ]);

    $opportunity->refresh();

    expect($opportunity->status)->toBe(OpportunityStatus::WON)
        ->and($opportunity->won_at)->not->toBeNull();
});
