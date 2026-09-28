<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PaymentTerms\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Modules\ERP\Filament\Resources\PaymentTerms\PaymentTermResource;
use Override;

final class CreatePaymentTerm extends CreateRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = PaymentTermResource::class;
}
