<?php

declare(strict_types=1);

return [
    'name' => 'ERP',
    'einvoice' => [
        'driver' => env('ERP_EINVOICE_DRIVER', 'stub'),
        'aruba' => [
            'base_url' => env('ERP_EINVOICE_ARUBA_BASE_URL'),
            'auth_base_url' => env('ERP_EINVOICE_ARUBA_AUTH_BASE_URL'),
            'upload_path' => env('ERP_EINVOICE_ARUBA_UPLOAD_PATH', '/services/invoice/upload'),
            'notifications_path' => env('ERP_EINVOICE_ARUBA_NOTIFICATIONS_PATH', '/api/v2/invoices-out/notifications'),
            'submit_path' => env('ERP_EINVOICE_ARUBA_SUBMIT_PATH', '/services/invoice/upload'),
            'status_path' => env('ERP_EINVOICE_ARUBA_STATUS_PATH', '/api/v2/invoices-out/notifications'),
            'token' => env('ERP_EINVOICE_ARUBA_TOKEN'),
            'username' => env('ERP_EINVOICE_ARUBA_USERNAME'),
            'password' => env('ERP_EINVOICE_ARUBA_PASSWORD'),
            'signature_credential' => env('ERP_EINVOICE_ARUBA_SIGNATURE_CREDENTIAL'),
            'signature_domain' => env('ERP_EINVOICE_ARUBA_SIGNATURE_DOMAIN'),
            'sender_piva' => env('ERP_EINVOICE_ARUBA_SENDER_PIVA'),
            'skip_extra_schema' => env('ERP_EINVOICE_ARUBA_SKIP_EXTRA_SCHEMA', false),
            'dry_run' => env('ERP_EINVOICE_ARUBA_DRY_RUN', false),
            'callback_api_key' => env('ERP_EINVOICE_ARUBA_CALLBACK_API_KEY'),
        ],
    ],
    'payment_requests' => [
        'driver' => env('ERP_PAYMENT_REQUEST_DRIVER', 'stub'),
        'providers' => [
            'stub' => ['callback_api_key' => env('ERP_PAYMENT_REQUEST_STUB_CALLBACK_API_KEY')],
        ],
    ],
];
