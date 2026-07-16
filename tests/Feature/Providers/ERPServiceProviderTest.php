<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Modules\ERP\Contracts\EInvoiceProvider;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\TaxCode;
use Modules\ERP\Policies\ERPModelPolicy;
use Modules\ERP\Providers\ERPServiceProvider;
use Modules\ERP\Services\EInvoice\ArubaEInvoiceProvider;
use Modules\ERP\Services\EInvoice\StubEInvoiceProvider;

it('binds the stub e-invoice provider for the default driver', function (): void {
    $provider = new ERPServiceProvider(app());
    $provider->register();

    config()->set('erp.einvoice.driver', 'stub');
    app()->forgetInstance(EInvoiceProvider::class);

    expect(app(EInvoiceProvider::class))->toBeInstanceOf(StubEInvoiceProvider::class);
});

it('falls back to the stub e-invoice provider for unknown drivers', function (): void {
    $provider = new ERPServiceProvider(app());
    $provider->register();

    config()->set('erp.einvoice.driver', 'unknown-provider');
    app()->forgetInstance(EInvoiceProvider::class);

    expect(app(EInvoiceProvider::class))->toBeInstanceOf(StubEInvoiceProvider::class);
});

it('binds the Aruba e-invoice provider for the aruba driver', function (): void {
    $provider = new ERPServiceProvider(app());
    $provider->register();

    config()->set('erp.einvoice.driver', 'aruba');
    app()->forgetInstance(EInvoiceProvider::class);

    expect(app(EInvoiceProvider::class))->toBeInstanceOf(ArubaEInvoiceProvider::class);
});

it('registers erp model policies on boot', function (): void {
    $provider = new ERPServiceProvider(app());

    expect(fn () => $provider->boot())->not->toThrow(Throwable::class)
        ->and(Gate::getPolicyFor(Company::class))->toBeInstanceOf(ERPModelPolicy::class)
        ->and(Gate::getPolicyFor(TaxCode::class))->toBeInstanceOf(ERPModelPolicy::class);
});
