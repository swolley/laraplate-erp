<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\ERP\Http\Controllers\EInvoiceProviderCallbackController;
use Modules\ERP\Http\Controllers\PaymentRequestProviderCallbackController;

Route::post('erp/einvoice/{provider}/callbacks', EInvoiceProviderCallbackController::class)
    ->whereIn('provider', ['aruba'])
    ->name('einvoice.provider-callback');

Route::post('erp/payment-requests/{provider}/callbacks', PaymentRequestProviderCallbackController::class)
    ->where('provider', '[a-z0-9_-]+')
    ->name('payment-requests.provider-callback');
