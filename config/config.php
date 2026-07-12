<?php

declare(strict_types=1);

return [
    'name' => 'ERP',
    'einvoice' => [
        'driver' => env('ERP_EINVOICE_DRIVER', 'stub'),
        'aruba' => [
            'base_url' => env('ERP_EINVOICE_ARUBA_BASE_URL'),
            'submit_path' => env('ERP_EINVOICE_ARUBA_SUBMIT_PATH', '/einvoices'),
            'status_path' => env('ERP_EINVOICE_ARUBA_STATUS_PATH', '/einvoices/{external_id}'),
            'token' => env('ERP_EINVOICE_ARUBA_TOKEN'),
        ],
    ],
];
