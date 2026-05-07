<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Payments\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\ERP\Filament\Resources\Payments\PaymentResource;
use Override;

final class CreatePayment extends CreateRecord
{
    #[Override]
    protected static string $resource = PaymentResource::class;
}
