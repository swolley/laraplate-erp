<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Casts\OpportunityStatus;
use Modules\ERP\Database\Seeders\ERPDatabaseSeeder;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Entity;
use Modules\ERP\Models\Opportunity;
use Modules\ERP\Models\OpportunityStage;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\Pivot\Presettable;
use Modules\ERP\Services\Reporting\SalesPipelineService;
use Modules\ERP\Tests\Stubs\OpportunityStatusDouble;
use Modules\ERP\Tests\Stubs\SalesPipelineOpportunityRowDouble;
use Modules\ERP\Tests\Stubs\SalesPipelineServiceStub;

uses(RefreshDatabase::class);

it('streams opportunity rows for pipeline aggregation', function (): void {
    $source = file_get_contents(base_path('Modules/ERP/app/Services/Reporting/SalesPipelineService.php'));

    expect($source)->not->toContain('->get([')
        ->and($source)->toContain('->lazy(500)');
});

it('creates buckets for unknown opportunity status values', function (): void {
    $legacy_row = new SalesPipelineOpportunityRowDouble(
        status: new OpportunityStatusDouble('archived'),
        expected_value_doc: '250.0000',
        expected_value_local: '200.0000',
        won_at: null,
        lost_at: null,
    );

    $result = (new SalesPipelineServiceStub(new Collection([$legacy_row])))->generate(99);

    expect($result['by_status']['archived']['count'])->toBe(1)
        ->and($result['by_status']['archived']['expected_value_doc'])->toBe('250.0000')
        ->and($result['by_status']['archived']['expected_value_local'])->toBe('200.0000')
        ->and($result['total_count'])->toBe(1);
});

it('aggregates pipeline totals and status buckets for opportunities', function (): void {
    $this->seed(ERPDatabaseSeeder::class);

    $company = Company::query()->withoutGlobalScopes()->where('is_default', true)->firstOrFail();
    $entity = Entity::query()->withoutGlobalScopes()->where('name', 'opportunity_stage')->firstOrFail();
    $presettable = Presettable::query()->where('entity_id', $entity->id)->firstOrFail();
    $stage = OpportunityStage::query()->forceCreate([
        'parent_id' => null,
        'presettable_id' => $presettable->id,
        'entity_id' => $entity->id,
    ]);
    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Customer',
        'is_customer' => true,
        'is_supplier' => false,
    ]);
    Opportunity::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'stage_taxonomy_id' => $stage->id,
        'name' => 'Deal A',
        'status' => OpportunityStatus::Open,
        'expected_value_doc' => '1000.0000',
        'expected_currency_doc' => 'EUR',
        'expected_value_local' => '1000.0000',
        'expected_currency_local' => 'EUR',
        'expected_fx_rate' => '1.0000',
        'won_at' => now(),
    ]);
    Opportunity::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'stage_taxonomy_id' => $stage->id,
        'name' => 'Deal B',
        'status' => OpportunityStatus::Lost,
        'expected_value_doc' => '500.0000',
        'expected_currency_doc' => 'EUR',
        'expected_value_local' => '500.0000',
        'expected_currency_local' => 'EUR',
        'expected_fx_rate' => '1.0000',
        'lost_at' => now(),
    ]);

    $result = app(SalesPipelineService::class)->generate((int) $company->id);

    expect($result['total_count'])->toBe(2)
        ->and($result['won_count'])->toBe(1)
        ->and($result['lost_count'])->toBe(1)
        ->and($result['by_status']['open']['count'])->toBe(1)
        ->and($result['by_status']['lost']['count'])->toBe(1)
        ->and($result['total_expected_value_doc'])->toBe('1500.0000');
});
