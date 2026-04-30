<?php

declare(strict_types=1);

namespace Modules\ERP\Data\EInvoice;

/**
 * Provider-agnostic remote status returned when polling a submission channel.
 */
enum EInvoiceRemoteStatus: string
{
    case UNKNOWN = 'unknown';
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case DELIVERED = 'delivered';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
}
