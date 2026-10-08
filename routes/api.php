<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\SelfAuthenticatedApiRoute;
use Modules\ERP\Http\Controllers\EInvoiceProviderCallbackController;
use Modules\ERP\Http\Controllers\PaymentRequestProviderCallbackController;

// The providers send their own secret as the bearer: the controllers check it, not Laraplate's API authentication.
Route::post('erp/einvoice/{provider}/callbacks', EInvoiceProviderCallbackController::class)
    ->middleware(SelfAuthenticatedApiRoute::class)
    ->whereIn('provider', ['aruba'])
    ->name('einvoice.provider-callback');

Route::post('erp/payment-requests/{provider}/callbacks', PaymentRequestProviderCallbackController::class)
    ->middleware(SelfAuthenticatedApiRoute::class)
    ->where('provider', '[a-z0-9_-]+')
    ->name('payment-requests.provider-callback');
