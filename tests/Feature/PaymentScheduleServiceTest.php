<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\PaymentDirection;
use Modules\ERP\Casts\PaymentScheduleStatus;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\Party;
use Modules\ERP\Models\Payment;
use Modules\ERP\Models\PaymentAllocation;
use Modules\ERP\Models\PaymentScheduleLine;
use Modules\ERP\Models\PaymentTerm;
use Modules\ERP\Models\TaxCode;
use Modules\ERP\Services\Payments\AgingReportService;
use Modules\ERP\Services\Payments\PaymentAllocationService;

uses(RefreshDatabase::class);

it('generates single schedule line when no payment term', function (): void {
    $company = Company::query()->create([
        'slug' => 'pay-sched-single',
        'name' => 'Pay Sched Single',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Buyer',
    ]);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => InvoiceDirection::Sale,
        'currency' => 'EUR',
    ]);

    $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Service',
        'quantity' => 1,
        'unit_price' => 1000,
    ]);

    $invoice->update(['posted_at' => now()]);
    $invoice->refresh();

    $schedule_lines = PaymentScheduleLine::query()
        ->where('invoice_id', (int) $invoice->getKey())
        ->get();

    expect($schedule_lines)->toHaveCount(1)
        ->and($schedule_lines->first()->due_date->toDateString())->toBe(now()->toDateString())
        ->and((float) $schedule_lines->first()->amount_doc)->toBe(1000.0)
        ->and($schedule_lines->first()->status)->toBe(PaymentScheduleStatus::Open);
});

it('generates multiple schedule lines from payment term rate_lines', function (): void {
    $company = Company::query()->create([
        'slug' => 'pay-sched-multi',
        'name' => 'Pay Sched Multi',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Buyer',
    ]);

    $payment_term = PaymentTerm::query()->create([
        'company_id' => $company->id,
        'name' => '30/60 split',
        'rate_lines' => [
            ['days' => 30, 'percent' => 50],
            ['days' => 60, 'percent' => 50],
        ],
        'is_active' => true,
    ]);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => InvoiceDirection::Sale,
        'currency' => 'EUR',
        'payment_term_id' => $payment_term->id,
    ]);

    $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Goods',
        'quantity' => 2,
        'unit_price' => 500,
    ]);

    $invoice->update(['posted_at' => now()]);
    $invoice->refresh();

    $schedule_lines = PaymentScheduleLine::query()
        ->where('invoice_id', (int) $invoice->getKey())
        ->orderBy('due_date')
        ->get();

    expect($schedule_lines)->toHaveCount(2)
        ->and($schedule_lines[0]->due_date->toDateString())->toBe(now()->addDays(30)->toDateString())
        ->and((float) $schedule_lines[0]->amount_doc)->toBe(500.0)
        ->and($schedule_lines[0]->status)->toBe(PaymentScheduleStatus::Open)
        ->and($schedule_lines[1]->due_date->toDateString())->toBe(now()->addDays(60)->toDateString())
        ->and((float) $schedule_lines[1]->amount_doc)->toBe(500.0)
        ->and($schedule_lines[1]->status)->toBe(PaymentScheduleStatus::Open);
});

it('removes schedule lines on unpost when no allocations', function (): void {
    $company = Company::query()->create([
        'slug' => 'pay-sched-unpost',
        'name' => 'Pay Sched Unpost',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Buyer',
    ]);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => InvoiceDirection::Sale,
        'currency' => 'EUR',
    ]);

    $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Widget',
        'quantity' => 1,
        'unit_price' => 200,
    ]);

    $invoice->update(['posted_at' => now()]);
    $invoice->refresh();

    expect(PaymentScheduleLine::query()->where('invoice_id', (int) $invoice->getKey())->count())->toBe(1);

    $invoice->update(['posted_at' => null]);

    expect(PaymentScheduleLine::query()->where('invoice_id', (int) $invoice->getKey())->count())->toBe(0);
});

it('prevents unpost when schedule line has payment allocated', function (): void {
    $company = Company::query()->create([
        'slug' => 'pay-sched-block',
        'name' => 'Pay Sched Block',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Buyer',
    ]);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => InvoiceDirection::Sale,
        'currency' => 'EUR',
    ]);

    $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Service',
        'quantity' => 1,
        'unit_price' => 300,
    ]);

    $invoice->update(['posted_at' => now()]);
    $invoice->refresh();

    $schedule_line = PaymentScheduleLine::query()
        ->where('invoice_id', (int) $invoice->getKey())
        ->firstOrFail();

    $payment = Payment::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => PaymentDirection::Inbound,
        'payment_date' => now()->toDateString(),
        'amount_doc' => '300.0000',
        'currency_doc' => 'EUR',
        'amount_local' => '300.0000',
        'currency_local' => 'EUR',
        'fx_rate' => '1.00000000',
    ]);

    $allocation_service = app(PaymentAllocationService::class);
    $allocation_service->allocate($payment, [(int) $schedule_line->getKey() => '300.0000']);

    expect(fn () => $invoice->update(['posted_at' => null]))
        ->toThrow(ValidationException::class);
});

it('allocates payment to schedule line and updates status to paid', function (): void {
    $company = Company::query()->create([
        'slug' => 'pay-alloc-full',
        'name' => 'Pay Alloc Full',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Buyer',
    ]);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => InvoiceDirection::Sale,
        'currency' => 'EUR',
    ]);

    $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Item',
        'quantity' => 1,
        'unit_price' => 500,
    ]);

    $invoice->update(['posted_at' => now()]);

    $schedule_line = PaymentScheduleLine::query()
        ->where('invoice_id', (int) $invoice->getKey())
        ->firstOrFail();

    $payment = Payment::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => PaymentDirection::Inbound,
        'payment_date' => now()->toDateString(),
        'amount_doc' => '500.0000',
        'currency_doc' => 'EUR',
        'amount_local' => '500.0000',
        'currency_local' => 'EUR',
        'fx_rate' => '1.00000000',
    ]);

    $allocation_service = app(PaymentAllocationService::class);
    $allocation_service->allocate($payment, [(int) $schedule_line->getKey() => '500.0000']);

    $schedule_line->refresh();

    expect($schedule_line->status)->toBe(PaymentScheduleStatus::Paid)
        ->and((float) $schedule_line->paid_amount_doc)->toBe(500.0)
        ->and($schedule_line->paid_at)->not->toBeNull();
});

it('partial allocation sets status to partial', function (): void {
    $company = Company::query()->create([
        'slug' => 'pay-alloc-partial',
        'name' => 'Pay Alloc Partial',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Buyer',
    ]);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => InvoiceDirection::Sale,
        'currency' => 'EUR',
    ]);

    $invoice->lines()->create([
        'line_no' => 1,
        'description' => 'Item',
        'quantity' => 1,
        'unit_price' => 400,
    ]);

    $invoice->update(['posted_at' => now()]);

    $schedule_line = PaymentScheduleLine::query()
        ->where('invoice_id', (int) $invoice->getKey())
        ->firstOrFail();

    $payment = Payment::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => PaymentDirection::Inbound,
        'payment_date' => now()->toDateString(),
        'amount_doc' => '200.0000',
        'currency_doc' => 'EUR',
        'amount_local' => '200.0000',
        'currency_local' => 'EUR',
        'fx_rate' => '1.00000000',
    ]);

    $allocation_service = app(PaymentAllocationService::class);
    $allocation_service->allocate($payment, [(int) $schedule_line->getKey() => '200.0000']);

    $schedule_line->refresh();

    expect($schedule_line->status)->toBe(PaymentScheduleStatus::Partial)
        ->and((float) $schedule_line->paid_amount_doc)->toBe(200.0)
        ->and($schedule_line->paid_at)->toBeNull();
});

it('aging report buckets correctly', function (): void {
    $company = Company::query()->create([
        'slug' => 'pay-aging',
        'name' => 'Pay Aging',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);

    $party = Party::query()->create([
        'company_id' => $company->id,
        'name' => 'Debtor',
    ]);

    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'party_id' => $party->id,
        'direction' => InvoiceDirection::Sale,
        'currency' => 'EUR',
        'posted_at' => now(),
        'journal_entry_id' => null,
    ]);

    $today = Carbon::today();

    PaymentScheduleLine::query()->create([
        'company_id' => $company->id,
        'invoice_id' => (int) $invoice->getKey(),
        'due_date' => $today->copy()->addDays(5)->toDateString(),
        'amount_doc' => '100.0000',
        'currency_doc' => 'EUR',
        'amount_local' => '100.0000',
        'currency_local' => 'EUR',
        'fx_rate' => '1.00000000',
        'paid_amount_doc' => '0.0000',
        'paid_amount_local' => '0.0000',
        'status' => PaymentScheduleStatus::Open,
    ]);

    PaymentScheduleLine::query()->create([
        'company_id' => $company->id,
        'invoice_id' => (int) $invoice->getKey(),
        'due_date' => $today->copy()->subDays(15)->toDateString(),
        'amount_doc' => '200.0000',
        'currency_doc' => 'EUR',
        'amount_local' => '200.0000',
        'currency_local' => 'EUR',
        'fx_rate' => '1.00000000',
        'paid_amount_doc' => '0.0000',
        'paid_amount_local' => '0.0000',
        'status' => PaymentScheduleStatus::Open,
    ]);

    PaymentScheduleLine::query()->create([
        'company_id' => $company->id,
        'invoice_id' => (int) $invoice->getKey(),
        'due_date' => $today->copy()->subDays(45)->toDateString(),
        'amount_doc' => '300.0000',
        'currency_doc' => 'EUR',
        'amount_local' => '300.0000',
        'currency_local' => 'EUR',
        'fx_rate' => '1.00000000',
        'paid_amount_doc' => '0.0000',
        'paid_amount_local' => '0.0000',
        'status' => PaymentScheduleStatus::Open,
    ]);

    PaymentScheduleLine::query()->create([
        'company_id' => $company->id,
        'invoice_id' => (int) $invoice->getKey(),
        'due_date' => $today->copy()->subDays(100)->toDateString(),
        'amount_doc' => '400.0000',
        'currency_doc' => 'EUR',
        'amount_local' => '400.0000',
        'currency_local' => 'EUR',
        'fx_rate' => '1.00000000',
        'paid_amount_doc' => '0.0000',
        'paid_amount_local' => '0.0000',
        'status' => PaymentScheduleStatus::Open,
    ]);

    $aging_service = app(AgingReportService::class);
    $report = $aging_service->generate((int) $company->id, 'receivable', $today);

    expect($report)->toHaveCount(1);

    $row = $report[0];

    expect((float) $row['current'])->toBe(100.0)
        ->and((float) $row['days_30'])->toBe(200.0)
        ->and((float) $row['days_60'])->toBe(300.0)
        ->and((float) $row['days_90'])->toBe(0.0)
        ->and((float) $row['days_120_plus'])->toBe(400.0)
        ->and((float) $row['total'])->toBe(1000.0);
});
