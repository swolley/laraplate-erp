<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Payments\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Modules\ERP\Filament\Resources\Payments\PaymentResource;
use Override;

final class CreatePayment extends CreateRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = PaymentResource::class;
}
