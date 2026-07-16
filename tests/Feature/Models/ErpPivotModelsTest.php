<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Contact;
use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Models\DeliveryNoteLine;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\Item;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\Pivot\Contactable;
use Modules\ERP\Models\Pivot\InvoiceLineHasDeliveryNoteLine;
use Modules\ERP\Models\Warehouse;

uses(RefreshDatabase::class);

function erpPivotCompany(): Company
{
    return Company::query()->create([
        'slug' => 'pivot-co-' . uniqid(),
        'name' => 'Pivot Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
}

it('hydrates invoice delivery note line links with the ERP pivot model', function (): void {
    $company = erpPivotCompany();
    $item = Item::query()->create([
        'company_id' => $company->id,
        'name' => 'Pivot Item',
        'sku' => 'PIVOT-' . uniqid(),
        'uom' => 'pcs',
        'costing_method' => 'fifo',
    ]);
    $warehouse = Warehouse::query()->create([
        'company_id' => $company->id,
        'name' => 'Pivot Warehouse',
        'code' => 'PIV-' . uniqid(),
    ]);
    $delivery_note = DeliveryNote::query()->create([
        'company_id' => $company->id,
        'reference' => 'PIVOT-DDT',
    ]);
    $delivery_note_line = DeliveryNoteLine::query()->create([
        'company_id' => $company->id,
        'delivery_note_id' => $delivery_note->id,
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => '5.0000',
    ]);
    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice->value,
        'currency' => 'EUR',
    ]);
    $invoice_line = $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Pivot Item',
        'quantity' => '2.5000',
        'unit_price' => '10.0000',
    ]);

    $invoice_line->delivery_note_lines()->attach($delivery_note_line->id, ['quantity' => '2.5000']);

    $linked_delivery_note_line = $invoice_line->delivery_note_lines()->firstOrFail();

    expect($linked_delivery_note_line->pivot)->toBeInstanceOf(InvoiceLineHasDeliveryNoteLine::class)
        ->and($linked_delivery_note_line->pivot->getTable())->toBe(ERPTables::InvoiceLineDeliveryNoteLine->value)
        ->and((string) $linked_delivery_note_line->pivot->quantity)->toBe('2.5000')
        ->and($linked_delivery_note_line->pivot->created_at)->not->toBeNull()
        ->and($linked_delivery_note_line->pivot->updated_at)->not->toBeNull();
});

it('hydrates party contact links with the ERP contactable pivot model', function (): void {
    $company = erpPivotCompany();
    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Pivot Party',
        'is_customer' => true,
    ]);
    $contact = Contact::query()->forceCreate([
        'company_id' => $company->id,
        'name' => 'Pivot Contact',
        'email' => 'pivot@example.test',
    ]);

    $party->contacts()->attach($contact->id);

    $linked_contact = $party->contacts()->firstOrFail();

    expect(DB::table(ERPTables::Contactables->value)->count())->toBe(1)
        ->and($linked_contact->pivot)->toBeInstanceOf(Contactable::class)
        ->and($linked_contact->pivot->getTable())->toBe(ERPTables::Contactables->value);
});
