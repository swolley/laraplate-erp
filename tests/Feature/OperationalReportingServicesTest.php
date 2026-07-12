<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Casts\OpportunityStatus;
use Modules\ERP\Filament\Pages\SalesPipelinePage;
use Modules\ERP\Filament\Pages\StockValuationPage;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\Opportunity;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\StockLevel;
use Modules\ERP\Models\Warehouse;
use Modules\ERP\Services\Reporting\OperationalReportCsvExporter;
use Modules\ERP\Services\Reporting\SalesPipelineService;
use Modules\ERP\Services\Reporting\StockValuationService;
use Modules\ERP\Tests\Support\OpportunityStageTaxonomy;

uses(RefreshDatabase::class);

it('summarizes sales pipeline values by opportunity status', function (): void {
    $stage_id = OpportunityStageTaxonomy::insertMinimalId('pipeline-summary');
    $company = Company::query()->where('slug', 'default')->firstOrFail();
    $other_company = Company::query()->create([
        'slug' => 'other-pipeline',
        'name' => 'Other Pipeline Company',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
        'is_default' => false,
    ]);
    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Customer',
        'is_customer' => true,
    ]);
    $other_party = Party::query()->create([
        'company_id' => $other_company->id,
        'name' => 'Other Customer',
        'is_customer' => true,
    ]);

    Opportunity::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'stage_taxonomy_id' => $stage_id,
        'name' => 'Open deal',
        'status' => OpportunityStatus::Open->value,
        'expected_value_doc' => '1000.5000',
        'expected_currency_doc' => 'EUR',
        'expected_value_local' => '1000.5000',
        'expected_currency_local' => 'EUR',
        'expected_fx_rate' => '1.00000000',
        'probability' => 50,
    ]);
    Opportunity::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'stage_taxonomy_id' => $stage_id,
        'name' => 'Won deal',
        'status' => OpportunityStatus::Won->value,
        'expected_value_doc' => '250.2500',
        'expected_currency_doc' => 'EUR',
        'expected_value_local' => '250.2500',
        'expected_currency_local' => 'EUR',
        'expected_fx_rate' => '1.00000000',
        'probability' => 100,
        'won_at' => now(),
    ]);
    Opportunity::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'stage_taxonomy_id' => $stage_id,
        'name' => 'Lost deal',
        'status' => OpportunityStatus::Lost->value,
        'expected_value_doc' => null,
        'expected_currency_doc' => 'EUR',
        'expected_value_local' => null,
        'expected_currency_local' => 'EUR',
        'expected_fx_rate' => '1.00000000',
        'probability' => 0,
        'lost_at' => now(),
    ]);
    Opportunity::query()->create([
        'company_id' => $other_company->id,
        'party_id' => $other_party->id,
        'stage_taxonomy_id' => $stage_id,
        'name' => 'Other company deal',
        'status' => OpportunityStatus::Open->value,
        'expected_value_doc' => '9999.0000',
        'expected_currency_doc' => 'EUR',
        'expected_value_local' => '9999.0000',
        'expected_currency_local' => 'EUR',
        'expected_fx_rate' => '1.00000000',
        'probability' => 10,
    ]);

    $result = app(SalesPipelineService::class)->generate((int) $company->id);

    expect($result['total_count'])->toBe(3)
        ->and($result['won_count'])->toBe(1)
        ->and($result['lost_count'])->toBe(1)
        ->and($result['total_expected_value_doc'])->toBe('1250.7500')
        ->and($result['total_expected_value_local'])->toBe('1250.7500')
        ->and($result['by_status'][OpportunityStatus::Open->value]['count'])->toBe(1)
        ->and($result['by_status'][OpportunityStatus::Open->value]['expected_value_local'])->toBe('1000.5000')
        ->and($result['by_status'][OpportunityStatus::Won->value]['count'])->toBe(1)
        ->and($result['by_status'][OpportunityStatus::Lost->value]['expected_value_local'])->toBe('0.0000')
        ->and($result['by_status'][OpportunityStatus::Cancelled->value]['count'])->toBe(0);
});

it('filters won pipeline metrics by won date range without dropping open pipeline', function (): void {
    $stage_id = OpportunityStageTaxonomy::insertMinimalId('pipeline-won-range');
    $company = Company::query()->where('slug', 'default')->firstOrFail();
    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Pipeline Filter Customer',
        'is_customer' => true,
    ]);

    Opportunity::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'stage_taxonomy_id' => $stage_id,
        'name' => 'Open current pipeline',
        'status' => OpportunityStatus::Open->value,
        'expected_value_doc' => '1000.0000',
        'expected_currency_doc' => 'EUR',
        'expected_value_local' => '1000.0000',
        'expected_currency_local' => 'EUR',
        'expected_fx_rate' => '1.00000000',
        'probability' => 50,
    ]);
    Opportunity::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'stage_taxonomy_id' => $stage_id,
        'name' => 'Won inside range',
        'status' => OpportunityStatus::Won->value,
        'expected_value_doc' => '250.0000',
        'expected_currency_doc' => 'EUR',
        'expected_value_local' => '250.0000',
        'expected_currency_local' => 'EUR',
        'expected_fx_rate' => '1.00000000',
        'probability' => 100,
        'won_at' => '2026-02-15 12:00:00',
    ]);
    Opportunity::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'stage_taxonomy_id' => $stage_id,
        'name' => 'Won outside range',
        'status' => OpportunityStatus::Won->value,
        'expected_value_doc' => '900.0000',
        'expected_currency_doc' => 'EUR',
        'expected_value_local' => '900.0000',
        'expected_currency_local' => 'EUR',
        'expected_fx_rate' => '1.00000000',
        'probability' => 100,
        'won_at' => '2025-12-31 12:00:00',
    ]);

    $result = app(SalesPipelineService::class)->generate((int) $company->id, [
        'won_from' => '2026-02-01',
        'won_to' => '2026-02-28',
    ]);

    expect($result['total_count'])->toBe(3)
        ->and($result['by_status'][OpportunityStatus::Open->value]['count'])->toBe(1)
        ->and($result['won_count'])->toBe(1)
        ->and($result['won_value_doc'])->toBe('250.0000')
        ->and($result['won_value_local'])->toBe('250.0000');
});

it('summarizes stock valuation from stock levels', function (): void {
    $company = Company::query()->create([
        'slug' => 'stock-valuation',
        'name' => 'Stock Valuation Company',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
        'is_default' => false,
    ]);
    $other_company = Company::query()->create([
        'slug' => 'other-stock-valuation',
        'name' => 'Other Stock Valuation Company',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
        'is_default' => false,
    ]);
    $warehouse = Warehouse::query()->create([
        'company_id' => $company->id,
        'name' => 'Main warehouse',
        'code' => 'MAIN',
    ]);
    $other_warehouse = Warehouse::query()->create([
        'company_id' => $other_company->id,
        'name' => 'Other warehouse',
        'code' => 'OTHER',
    ]);
    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Tracked item',
        'sku' => 'SKU-1',
        'uom' => 'pcs',
        'costing_method' => 'weighted_avg',
    ]);
    $second_item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Second item',
        'sku' => 'SKU-2',
        'uom' => 'pcs',
        'costing_method' => 'fifo',
    ]);
    $other_item = Item::query()->create([
        'company_id' => $other_company->id,
        'name' => 'Other item',
        'sku' => 'SKU-3',
        'uom' => 'pcs',
        'costing_method' => 'weighted_avg',
    ]);

    StockLevel::query()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => '10.5000',
        'weighted_avg_cost' => '3.2500',
    ]);
    StockLevel::query()->create([
        'company_id' => $company->id,
        'item_id' => $second_item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => '2.0000',
        'weighted_avg_cost' => '10.0000',
    ]);
    StockLevel::query()->create([
        'company_id' => $other_company->id,
        'item_id' => $other_item->id,
        'warehouse_id' => $other_warehouse->id,
        'quantity' => '99.0000',
        'weighted_avg_cost' => '99.0000',
    ]);

    $result = app(StockValuationService::class)->generate((int) $company->id);

    expect($result['total_quantity'])->toBe('12.5000')
        ->and($result['total_value'])->toBe('54.1250')
        ->and($result['rows'])->toHaveCount(2)
        ->and($result['rows'][0]['sku'])->toBe('SKU-1')
        ->and($result['rows'][0]['quantity'])->toBe('10.5000')
        ->and($result['rows'][0]['weighted_avg_cost'])->toBe('3.2500')
        ->and($result['rows'][0]['value'])->toBe('34.1250')
        ->and($result['rows'][1]['sku'])->toBe('SKU-2')
        ->and($result['rows'][1]['value'])->toBe('20.0000');
});

it('filters stock valuation by warehouse', function (): void {
    $company = Company::query()->create([
        'slug' => 'stock-valuation-filter',
        'name' => 'Stock Valuation Filter Company',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
        'is_default' => false,
    ]);
    $main_warehouse = Warehouse::query()->create([
        'company_id' => $company->id,
        'name' => 'Main warehouse',
        'code' => 'MAIN',
    ]);
    $secondary_warehouse = Warehouse::query()->create([
        'company_id' => $company->id,
        'name' => 'Secondary warehouse',
        'code' => 'SEC',
    ]);
    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Filtered item',
        'sku' => 'SKU-F',
        'uom' => 'pcs',
        'costing_method' => 'weighted_avg',
    ]);

    StockLevel::query()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'warehouse_id' => $main_warehouse->id,
        'quantity' => '4.0000',
        'weighted_avg_cost' => '5.0000',
    ]);
    StockLevel::query()->create([
        'company_id' => $company->id,
        'item_id' => $item->id,
        'warehouse_id' => $secondary_warehouse->id,
        'quantity' => '10.0000',
        'weighted_avg_cost' => '7.0000',
    ]);

    $result = app(StockValuationService::class)->generate((int) $company->id, [
        'warehouse_id' => (int) $main_warehouse->id,
    ]);

    expect($result['rows'])->toHaveCount(1)
        ->and($result['rows'][0]['warehouse_code'])->toBe('MAIN')
        ->and($result['total_quantity'])->toBe('4.0000')
        ->and($result['total_value'])->toBe('20.0000');
});

it('exports operational reports as csv', function (): void {
    $exporter = app(OperationalReportCsvExporter::class);

    $pipeline_csv = $exporter->salesPipeline([
        'by_status' => [
            OpportunityStatus::Open->value => [
                'status' => OpportunityStatus::Open->value,
                'count' => 1,
                'expected_value_doc' => '1000.0000',
                'expected_value_local' => '1000.0000',
            ],
        ],
        'total_count' => 1,
        'won_count' => 0,
        'lost_count' => 0,
        'won_value_doc' => '0.0000',
        'won_value_local' => '0.0000',
        'total_expected_value_doc' => '1000.0000',
        'total_expected_value_local' => '1000.0000',
    ]);
    $stock_csv = $exporter->stockValuation([
        'rows' => [
            [
                'sku' => 'SKU-1',
                'item_name' => 'Item, one',
                'warehouse_code' => 'MAIN',
                'warehouse_name' => 'Main warehouse',
                'quantity' => '2.0000',
                'weighted_avg_cost' => '5.0000',
                'value' => '10.0000',
            ],
        ],
        'total_quantity' => '2.0000',
        'total_value' => '10.0000',
    ]);

    expect($pipeline_csv)->toContain('Status,Count,"Expected doc","Expected local"')
        ->and($pipeline_csv)->toContain('open,1,1000.0000,1000.0000')
        ->and($stock_csv)->toContain('SKU,"Item name","Warehouse code","Warehouse name",Quantity,"Weighted avg cost",Value')
        ->and($stock_csv)->toContain('SKU-1,"Item, one",MAIN,"Main warehouse",2.0000,5.0000,10.0000');
});

it('operational report pages expose csv export actions', function (): void {
    expect(method_exists(SalesPipelinePage::class, 'exportCsv'))->toBeTrue()
        ->and(method_exists(StockValuationPage::class, 'exportCsv'))->toBeTrue();
});
