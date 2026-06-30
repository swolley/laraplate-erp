<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\PaymentScheduleLine;
use Modules\ERP\Models\PaymentTerm;
use Modules\ERP\Services\Payments\PaymentScheduleGeneratorService;

uses(RefreshDatabase::class);

function createPaymentScheduleGeneratorCompany(): Company
{
    return Company::query()->create([
        'slug' => 'pay-sched-' . uniqid(),
        'name' => 'Payment Schedule Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
}

function createPaymentScheduleGeneratorInvoice(Company $company, ?int $payment_term_id = null): Invoice
{
    return Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice->value,
        'currency' => 'EUR',
        'posted_at' => CarbonImmutable::parse('2026-03-15 12:00:00'),
        'payment_term_id' => $payment_term_id,
    ]);
}

it('generates a single open line when no payment term is set', function (): void {
    $company = createPaymentScheduleGeneratorCompany();
    $invoice = createPaymentScheduleGeneratorInvoice($company);

    app(PaymentScheduleGeneratorService::class)->generate($invoice, '1500.0000');

    $line = PaymentScheduleLine::query()->where('invoice_id', $invoice->id)->sole();

    expect($line->due_date->toDateString())->toBe('2026-03-15')
        ->and((string) $line->amount_doc)->toBe('1500.0000')
        ->and($line->status->value)->toBe('open');
});

it('generates split schedule lines from payment term rate lines', function (): void {
    $company = createPaymentScheduleGeneratorCompany();
    $payment_term = PaymentTerm::query()->create([
        'company_id' => $company->id,
        'name' => '30/70',
        'rate_lines' => [
            ['days' => 30, 'percent' => 70],
            ['days' => 60, 'percent' => 30],
        ],
        'is_active' => true,
    ]);
    $invoice = createPaymentScheduleGeneratorInvoice($company, (int) $payment_term->id);

    app(PaymentScheduleGeneratorService::class)->generate($invoice, '1000.0000');

    $lines = PaymentScheduleLine::query()
        ->where('invoice_id', $invoice->id)
        ->orderBy('due_date')
        ->get();

    expect($lines)->toHaveCount(2)
        ->and($lines[0]->due_date->toDateString())->toBe('2026-04-14')
        ->and((string) $lines[0]->amount_doc)->toBe('700.0000')
        ->and($lines[1]->due_date->toDateString())->toBe('2026-05-14')
        ->and((string) $lines[1]->amount_doc)->toBe('300.0000');
});

it('normalizes negative gross totals to positive amounts', function (): void {
    $company = createPaymentScheduleGeneratorCompany();
    $invoice = createPaymentScheduleGeneratorInvoice($company);

    app(PaymentScheduleGeneratorService::class)->generate($invoice, '-250.5000');

    $line = PaymentScheduleLine::query()->where('invoice_id', $invoice->id)->sole();

    expect((string) $line->amount_doc)->toBe('250.5000');
});

it('parses non-immutable posted_at values when generating schedules', function (): void {
    $company = createPaymentScheduleGeneratorCompany();
    $invoice = Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice->value,
        'currency' => 'EUR',
        'payment_term_id' => null,
    ]);
    $invoice->setRawAttributes(array_merge($invoice->getAttributes(), [
        'posted_at' => '2026-06-01 08:30:00',
    ]));

    app(PaymentScheduleGeneratorService::class)->generate($invoice, '100.0000');

    $line = PaymentScheduleLine::query()->where('invoice_id', $invoice->id)->sole();

    expect($line->due_date->toDateString())->toBe('2026-06-01');
});

it('removes schedule lines when no allocations exist', function (): void {
    $company = createPaymentScheduleGeneratorCompany();
    $invoice = createPaymentScheduleGeneratorInvoice($company);

    $service = app(PaymentScheduleGeneratorService::class);
    $service->generate($invoice, '500.0000');

    expect(PaymentScheduleLine::query()->where('invoice_id', $invoice->id)->count())->toBe(1);

    $service->removeAll($invoice);

    expect(PaymentScheduleLine::query()->where('invoice_id', $invoice->id)->count())->toBe(0);
});

it('rejects removing schedule lines that already have allocations', function (): void {
    $company = createPaymentScheduleGeneratorCompany();
    $invoice = createPaymentScheduleGeneratorInvoice($company);
    $service = app(PaymentScheduleGeneratorService::class);
    $service->generate($invoice, '500.0000');

    PaymentScheduleLine::query()
        ->where('invoice_id', $invoice->id)
        ->update(['paid_amount_doc' => '100.0000']);

    expect(fn () => $service->removeAll($invoice))
        ->toThrow(ValidationException::class);
});
