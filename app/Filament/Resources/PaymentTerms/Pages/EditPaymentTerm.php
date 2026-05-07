<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PaymentTerms\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\ERP\Filament\Resources\PaymentTerms\PaymentTermResource;
use Override;

final class EditPaymentTerm extends EditRecord
{
    #[Override]
    protected static string $resource = PaymentTermResource::class;
}
