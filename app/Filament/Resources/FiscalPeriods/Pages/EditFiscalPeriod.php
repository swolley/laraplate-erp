<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\FiscalPeriods\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\ERP\Filament\Resources\FiscalPeriods\Actions\FiscalPeriodActions;
use Modules\ERP\Filament\Resources\FiscalPeriods\FiscalPeriodResource;
use Override;

final class EditFiscalPeriod extends EditRecord
{
    #[Override]
    protected static string $resource = FiscalPeriodResource::class;

    /**
     * @return array<int, \Filament\Actions\Action>
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            FiscalPeriodActions::close(),
            FiscalPeriodActions::reopen(),
            DeleteAction::make(),
        ];
    }
}
