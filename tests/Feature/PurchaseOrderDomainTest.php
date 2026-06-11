<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\DocumentType;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DocumentSequence;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\PurchaseOrder;
use Modules\ERP\Models\PurchaseOrderLine;
use Modules\ERP\Services\Accounting\DocumentNumberAllocator;

uses(RefreshDatabase::class);

it('allocates purchase order document numbers with gap_allowed like sales orders', function (): void {
    $company = Company::query()->create([
        'slug' => 'po-seq',
        'name' => 'PO Seq',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $allocator = new DocumentNumberAllocator;

    expect($allocator->next($company, DocumentType::PurchaseOrder, 0))->toBe('00001')
        ->and($allocator->next($company, DocumentType::PurchaseOrder, 0))->toBe('00002');

    $row = DocumentSequence::query()->withoutGlobalScopes()
        ->where('company_id', $company->id)
        ->where('document_type', DocumentType::PurchaseOrder)
        ->firstOrFail();

    expect((bool) $row->gap_allowed)->toBeTrue();
});

it('aggregates line counts and quantity sums for purchase order list queries', function (): void {
    $company = Company::query()->create([
        'slug' => 'po-agg',
        'name' => 'PO Agg',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Supplier',
        'is_supplier' => true,
    ]);

    $po = PurchaseOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'currency' => 'EUR',
        'status' => 'draft',
    ]);

    $po->lines()->create([
        'name' => 'A',
        'qty_ordered' => 3,
        'qty_received' => 1,
    ]);
    $po->lines()->create([
        'name' => 'B',
        'qty_ordered' => 5,
        'qty_received' => 2,
    ]);

    $aggregated = PurchaseOrder::query()
        ->withCount('lines')
        ->withSum('lines', 'qty_ordered')
        ->withSum('lines', 'qty_received')
        ->whereKey($po->id)
        ->firstOrFail();

    expect($aggregated->lines_count)->toBe(2)
        ->and((float) $aggregated->lines_sum_qty_ordered)->toBe(8.0)
        ->and((float) $aggregated->lines_sum_qty_received)->toBe(3.0);
});

it('rejects a purchase order whose party belongs to another company', function (): void {
    $company_a = Company::query()->create([
        'slug' => 'po-a',
        'name' => 'Po A',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $company_b = Company::query()->create([
        'slug' => 'po-b',
        'name' => 'Po B',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $party_b = Party::query()->create([
        'company_id' => $company_b->id,
        'name' => 'Supplier B',
        'is_supplier' => true,
    ]);

    expect(fn () => PurchaseOrder::query()->create([
        'company_id' => $company_a->id,
        'party_id' => $party_b->id,
        'currency' => 'EUR',
        'status' => 'draft',
    ]))->toThrow(ValidationException::class);
});

it('blocks qty_ordered changes after receipt progress on a purchase order line', function (): void {
    $company = Company::query()->create([
        'slug' => 'po-line',
        'name' => 'Po Line',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Supplier',
        'is_supplier' => true,
    ]);

    $po = PurchaseOrder::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'currency' => 'EUR',
        'status' => 'draft',
    ]);

    $line = PurchaseOrderLine::query()->create([
        'purchase_order_id' => $po->id,
        'name' => 'Widget',
        'qty_ordered' => 10,
        'qty_received' => 2,
    ]);

    $line->qty_ordered = 12;

    expect(fn () => $line->save())->toThrow(ValidationException::class);
});

it('rejects a purchase order line item from another company', function (): void {
    $company_a = Company::query()->create([
        'slug' => 'po-item-a',
        'name' => 'Po Item A',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $company_b = Company::query()->create([
        'slug' => 'po-item-b',
        'name' => 'Po Item B',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $party = Party::query()->create([
        'company_id' => $company_a->id,
        'name' => 'Supplier',
        'is_supplier' => true,
    ]);

    $item = Item::query()->create([
        'company_id' => $company_b->id,
        'name' => 'External',
        'sku' => 'EXT',
        'uom' => 'pcs',
        'costing_method' => 'weighted_avg',
    ]);

    $po = PurchaseOrder::query()->create([
        'company_id' => $company_a->id,
        'party_id' => $party->id,
        'currency' => 'EUR',
        'status' => 'draft',
    ]);

    expect(fn () => PurchaseOrderLine::query()->create([
        'purchase_order_id' => $po->id,
        'item_id' => $item->id,
        'name' => 'Widget',
        'qty_ordered' => 1,
        'qty_received' => 0,
    ]))->toThrow(ValidationException::class);
});
