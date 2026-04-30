<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\ERP\Casts\LeadStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Lead;

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
