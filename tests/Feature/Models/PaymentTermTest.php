<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\PaymentTerm;

uses(RefreshDatabase::class);

it('defines invoices relationship', function (): void {
    $relation = (new PaymentTerm)->invoices();

    expect($relation)->toBeInstanceOf(HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Invoice::class);
});

it('loads invoices linked to a payment term', function (): void {
    $company = Company::query()->create([
        'slug' => 'pay-term-' . uniqid(),
        'name' => 'Payment Term Co',
        'fiscal_country' => 'IT',
        'default_currency' => 'EUR',
    ]);
    $payment_term = PaymentTerm::query()->create([
        'company_id' => $company->id,
        'name' => 'Net 30',
        'rate_lines' => [['days' => 30, 'percent' => 100]],
        'is_active' => true,
    ]);
    Invoice::query()->create([
        'company_id' => $company->id,
        'direction' => InvoiceDirection::Sale,
        'invoice_type' => InvoiceType::Invoice->value,
        'currency' => 'EUR',
        'payment_term_id' => $payment_term->id,
    ]);

    expect($payment_term->invoices)->toHaveCount(1);
});
