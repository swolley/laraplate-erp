<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PaymentTerms\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\ERP\Filament\Resources\PaymentTerms\PaymentTermResource;
use Override;

final class CreatePaymentTerm extends CreateRecord
{
    #[Override]
    protected static string $resource = PaymentTermResource::class;
}
