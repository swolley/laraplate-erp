<?php

declare(strict_types=1);

use Modules\ERP\Contracts\EInvoiceProvider;
use Modules\ERP\Providers\ERPServiceProvider;
use Modules\ERP\Services\EInvoice\ArubaEInvoiceProvider;
use Modules\ERP\Services\EInvoice\StubEInvoiceProvider;

it('binds the stub e-invoice provider for the default driver', function (): void {
    config()->set('erp.einvoice.driver', 'stub');

    $provider = new ERPServiceProvider(app());
    $provider->register();

    expect(app(EInvoiceProvider::class))->toBeInstanceOf(StubEInvoiceProvider::class);
});

it('falls back to the stub e-invoice provider for unknown drivers', function (): void {
    config()->set('erp.einvoice.driver', 'unknown-provider');

    $provider = new ERPServiceProvider(app());
    $provider->register();

    expect(app(EInvoiceProvider::class))->toBeInstanceOf(StubEInvoiceProvider::class);
});

it('binds the Aruba e-invoice provider for the aruba driver', function (): void {
    config()->set('erp.einvoice.driver', 'aruba');

    $provider = new ERPServiceProvider(app());
    $provider->register();

    expect(app(EInvoiceProvider::class))->toBeInstanceOf(ArubaEInvoiceProvider::class);
});

it('registers erp model policies on boot', function (): void {
    $provider = new ERPServiceProvider(app());

    expect(fn () => $provider->boot())->not->toThrow(Throwable::class);
});
