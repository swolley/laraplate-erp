<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Payments\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\ERP\Filament\Resources\Payments\PaymentResource;
use Override;

final class EditPayment extends EditRecord
{
    #[Override]
    protected static string $resource = PaymentResource::class;
}
