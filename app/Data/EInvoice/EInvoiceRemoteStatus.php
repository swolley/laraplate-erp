<?php

declare(strict_types=1);

namespace Modules\ERP\Data\EInvoice;

/**
 * Provider-agnostic remote status returned when polling a submission channel.
 */
enum EInvoiceRemoteStatus: string
{
    case Unknown = 'unknown';
    case Pending = 'pending';
    case Processing = 'processing';
    case Delivered = 'delivered';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
