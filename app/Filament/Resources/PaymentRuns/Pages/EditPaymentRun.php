<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PaymentRuns\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\ERP\Filament\Resources\PaymentRuns\Actions\PaymentRunActions;
use Modules\ERP\Filament\Resources\PaymentRuns\PaymentRunResource;
use Override;

final class EditPaymentRun extends EditRecord
{
    #[Override]
    protected static string $resource = PaymentRunResource::class;

    /**
     * @return array<int, \Filament\Actions\Action>
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            PaymentRunActions::approve(),
            PaymentRunActions::exportSepa(),
            PaymentRunActions::exportCbiBonifici(),
            PaymentRunActions::cancel(),
        ];
    }
}
