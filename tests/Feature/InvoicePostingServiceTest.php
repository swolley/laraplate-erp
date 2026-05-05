<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\SalesOrderLineStatus;
use Modules\ERP\Casts\SalesOrderStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Customer;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Models\SalesOrderLine;
use Modules\ERP\Models\TaxCode;

uses(RefreshDatabase::class);

it('posts invoice journal, snapshots tax, and updates sales order invoiced quantities', function (): void {
    $company = Company::query()->create([
        'slug' => 'inv-sale',
        'name' => 'Inv Sale',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $customer = Customer::query()->create([
        'company_id' => $company->id,
        'name' => 'Buyer',
    ]);

    $order = SalesOrder::query()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'currency' => 'EUR',
        'status' => SalesOrderStatus::CONFIRMED,
    ]);

    $so_line = SalesOrderLine::query()->create([
        'sales_order_id' => $order->id,
        'name' => 'Part',
        'qty_ordered' => 5,
        'qty_delivered' => 0,
        'qty_invoiced' => 0,
        'status' => SalesOrderLineStatus::OPEN,
    ]);

    $vat = TaxCode::query()->create([
        'company_id' => $company->id,
        'code' => 'VAT22',
        'kind' => 'vat',
        'country' => 'IT',
        'rate' => 22,
        'label' => 'IVA 22%',
        'is_active' => true,
        'effective_from' => now()->toDateString(),
    ]);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Sale,
        'currency' => 'EUR',
    ]);

    $invoice->lines()->create([
        'line_no' => 1,
        'sales_order_line_id' => $so_line->id,
        'description' => 'Part',
        'quantity' => 2,
        'unit_price' => 100,
        'tax_code_id' => $vat->id,
    ]);

    $invoice->update(['posted_at' => now()]);
    $invoice->refresh();
    $so_line->refresh();

    expect($invoice->journal_entry_id)->not->toBeNull()
        ->and($so_line->qty_invoiced)->toBe(2)
        ->and($so_line->status)->toBe(SalesOrderLineStatus::PARTIALLY_EVASED);

    $line = $invoice->lines()->firstOrFail();
    expect($line->tax_code)->toBe('VAT22')
        ->and((string) $line->tax_rate)->toBe('22.0000')
        ->and($line->tax_label)->toBe('IVA 22%');

    $journal = JournalEntry::query()->withoutGlobalScopes()->findOrFail((int) $invoice->journal_entry_id);
    expect($journal->lines)->toHaveCount(3);
});

it('reverses invoice posting and rolls back invoiced quantities when unposted', function (): void {
    $company = Company::query()->create([
        'slug' => 'inv-unpost',
        'name' => 'Inv Unpost',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $customer = Customer::query()->create([
        'company_id' => $company->id,
        'name' => 'Buyer',
    ]);

    $order = SalesOrder::query()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'currency' => 'EUR',
        'status' => SalesOrderStatus::CONFIRMED,
    ]);

    $so_line = SalesOrderLine::query()->create([
        'sales_order_id' => $order->id,
        'name' => 'Part',
        'qty_ordered' => 3,
        'qty_delivered' => 0,
        'qty_invoiced' => 0,
        'status' => SalesOrderLineStatus::OPEN,
    ]);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Sale,
        'currency' => 'EUR',
    ]);

    $invoice->lines()->create([
        'line_no' => 1,
        'sales_order_line_id' => $so_line->id,
        'description' => 'Part',
        'quantity' => 3,
        'unit_price' => 10,
    ]);

    $invoice->update(['posted_at' => now()]);
    $posted_journal_id = (int) $invoice->fresh()->journal_entry_id;

    $invoice->update(['posted_at' => null]);
    $invoice->refresh();
    $so_line->refresh();

    expect($invoice->journal_entry_id)->toBeNull()
        ->and($so_line->qty_invoiced)->toBe(0)
        ->and($so_line->status)->toBe(SalesOrderLineStatus::OPEN);

    $reversal = JournalEntry::query()->withoutGlobalScopes()
        ->where('reverses_journal_entry_id', $posted_journal_id)
        ->first();
    expect($reversal)->not->toBeNull();
});
