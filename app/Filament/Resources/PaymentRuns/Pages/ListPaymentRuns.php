<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PaymentRuns\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\ERP\Filament\Resources\PaymentRuns\PaymentRunResource;
use Override;

final class ListPaymentRuns extends ListRecords
{
    #[Override]
    protected static string $resource = PaymentRunResource::class;
}
