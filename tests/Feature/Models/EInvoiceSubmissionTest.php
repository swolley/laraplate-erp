<?php

declare(strict_types=1);

use Modules\ERP\Models\EInvoiceSubmission;

it('does not create versions for e-invoice submissions', function (): void {
    $method = new ReflectionMethod(EInvoiceSubmission::class, 'shouldVersioning');
    $method->setAccessible(true);

    expect($method->invoke(new EInvoiceSubmission))->toBeFalse();
});
