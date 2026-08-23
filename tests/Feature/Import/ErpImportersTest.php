<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Import\Enums\ImportSourceFormat;
use Modules\Core\Import\Support\ImportRunner;
use Modules\Core\Models\ImportSession;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\Party;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    Company::query()->create([
        'slug' => 'acme',
        'name' => 'Acme',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
});

/**
 * @param  array<string, string>  $mapping
 */
function erpImportSession(string $entityKey, string $csv, array $mapping): ImportSession
{
    Storage::disk('local')->put('erp.csv', $csv);

    return ImportSession::factory()->create([
        'entity_key' => $entityKey,
        'source_format' => ImportSourceFormat::Csv,
        'file_disk' => 'local',
        'file_path' => 'erp.csv',
        'original_filename' => 'erp.csv',
        'mapping' => $mapping,
    ]);
}

test('the item importer upserts by (company, sku) and rejects an unknown company', function (): void {
    $mapping = ['sku' => 'sku', 'name' => 'name', 'uom' => 'uom', 'costing_method' => 'costing_method', 'company' => 'company'];

    $session = erpImportSession('erp.item',
        "sku,name,uom,costing_method,company\n"
        . "W-1,Widget,pcs,weighted_avg,acme\n"
        . "W-1,Widget v2,pcs,fifo,acme\n"
        . "BAD,Orphan,pcs,fifo,ghost\n",
        $mapping,
    );

    app(ImportRunner::class)->process($session);
    $session->refresh();

    expect($session->created_rows)->toBe(1)
        ->and($session->updated_rows)->toBe(1)
        ->and($session->failed_rows)->toBe(1)
        ->and(Item::query()->count())->toBe(1)
        ->and(Item::query()->where('sku', 'W-1')->value('name'))->toBe('Widget v2')
        ->and(Item::query()->where('sku', 'W-1')->value('costing_method'))->toBe('fifo')
        ->and($session->rowErrors()->first()->errors)->toHaveKey('company');
});

test('the party importer dedupes by VAT within the company and parses flags', function (): void {
    $mapping = ['name' => 'name', 'vat_number' => 'vat_number', 'is_customer' => 'is_customer', 'company' => 'company'];

    app(ImportRunner::class)->process(erpImportSession('erp.party',
        "name,vat_number,is_customer,company\n"
        . "Acme Spa,IT123,1,acme\n"
        . "No VAT,,yes,acme\n",
        $mapping,
    ));

    expect(Party::query()->count())->toBe(2)
        ->and(Party::query()->where('vat_number', 'IT123')->value('is_customer'))->toBeTruthy();

    // Re-import the VAT-matched party with a new name → updated, not duplicated.
    $second = erpImportSession('erp.party', "name,vat_number,company\nAcme Group,IT123,acme\n",
        ['name' => 'name', 'vat_number' => 'vat_number', 'company' => 'company']);
    app(ImportRunner::class)->process($second);

    expect($second->fresh()->updated_rows)->toBe(1)
        ->and(Party::query()->count())->toBe(2)
        ->and(Party::query()->where('vat_number', 'IT123')->value('name'))->toBe('Acme Group');
});
