<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Payments\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\ERP\Filament\Resources\Payments\PaymentResource;
use Override;

final class ListPayments extends ListRecords
{
    #[Override]
    protected static string $resource = PaymentResource::class;
}
