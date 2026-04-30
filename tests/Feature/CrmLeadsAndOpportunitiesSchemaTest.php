<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\ERP\Casts\LeadStatus;
use Modules\ERP\Casts\OpportunityStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Customer;
use Modules\ERP\Models\Lead;
use Modules\ERP\Models\Opportunity;
use Modules\ERP\Tests\Support\OpportunityStageTaxonomy;

uses(RefreshDatabase::class);

it('creates leads and opportunities tables with quotation opportunity link', function (): void {
    expect(Schema::hasTable('leads'))->toBeTrue()
        ->and(Schema::hasTable('opportunities'))->toBeTrue()
        ->and(Schema::hasColumn('quotations', 'opportunity_id'))->toBeTrue();
});

it('persists a lead with company scope fields', function (): void {
    $company = Company::query()->create([
        'slug' => 'crm-co',
        'name' => 'CRM Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $lead = Lead::query()->create([
        'company_id' => $company->id,
        'title' => 'Inbound widget inquiry',
        'status' => LeadStatus::NEW,
        'source' => 'web',
    ]);

    expect($lead->company_id)->toBe($company->id)
        ->and($lead->status)->toBe(LeadStatus::NEW)
        ->and($company->leads)->toHaveCount(1);
});

it('persists an opportunity when pipeline taxonomy exists', function (): void {
    $stage_id = OpportunityStageTaxonomy::insertMinimalId('opp-test-new');

    $company = Company::query()->withoutGlobalScopes()->where('is_default', true)->firstOrFail();

    $customer = Customer::query()->create([
        'company_id' => $company->id,
        'name' => 'CRM buyer',
    ]);

    $opportunity = Opportunity::query()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'stage_taxonomy_id' => $stage_id,
        'name' => 'Enterprise rollout',
        'status' => OpportunityStatus::OPEN,
        'expected_currency_doc' => 'EUR',
        'expected_currency_local' => 'EUR',
        'expected_fx_rate' => 1,
    ]);

    expect($opportunity->customer_id)->toBe($customer->id)
        ->and($opportunity->stage_taxonomy_id)->toBe($stage_id);
});
