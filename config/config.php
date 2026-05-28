<?php

declare(strict_types=1);

return [
    'name' => 'ERP',
    'einvoice' => [
        'driver' => env('ERP_EINVOICE_DRIVER', 'stub'),
    ],
];
