<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\ERP\Casts\LeadStatus;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Casts\OpportunityStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\Lead;
use Modules\ERP\Models\Opportunity;
use Modules\ERP\Tests\Support\OpportunityStageTaxonomy;

uses(RefreshDatabase::class);

it('creates leads and opportunities tables with quotation opportunity link', function (): void {
    expect(Schema::hasTable(ERPTables::Leads->value))->toBeTrue()
        ->and(Schema::hasTable(ERPTables::Opportunities->value))->toBeTrue()
        ->and(Schema::hasColumn(ERPTables::Quotations->value, 'opportunity_id'))->toBeTrue();
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
        'status' => LeadStatus::New,
        'source' => 'web',
    ]);

    expect($lead->company_id)->toBe($company->id)
        ->and($lead->status)->toBe(LeadStatus::New)
        ->and($company->leads)->toHaveCount(1);
});

it('persists an opportunity when pipeline taxonomy exists', function (): void {
    $stage_id = OpportunityStageTaxonomy::insertMinimalId('opp-test-new');

    $company = Company::query()->withoutGlobalScopes()->where('is_default', true)->firstOrFail();

    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'CRM buyer',
    ]);

    $opportunity = Opportunity::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'stage_taxonomy_id' => $stage_id,
        'name' => 'Enterprise rollout',
        'status' => OpportunityStatus::Open,
        'expected_currency_doc' => 'EUR',
        'expected_currency_local' => 'EUR',
        'expected_fx_rate' => 1,
    ]);

    expect($opportunity->party_id)->toBe($party->id)
        ->and($opportunity->stage_taxonomy_id)->toBe($stage_id);
});
