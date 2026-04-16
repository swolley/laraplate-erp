<?php

declare(strict_types=1);

namespace Modules\Business\Casts;

enum QuoteStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
}
