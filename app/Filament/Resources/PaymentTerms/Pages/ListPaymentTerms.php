<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PaymentTerms\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\ERP\Filament\Resources\PaymentTerms\PaymentTermResource;
use Override;

final class ListPaymentTerms extends ListRecords
{
    #[Override]
    protected static string $resource = PaymentTermResource::class;
}
