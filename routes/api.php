<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\ERP\Http\Controllers\EInvoiceProviderCallbackController;

Route::post('erp/einvoice/{provider}/callbacks', EInvoiceProviderCallbackController::class)
    ->whereIn('provider', ['aruba'])
    ->name('einvoice.provider-callback');
