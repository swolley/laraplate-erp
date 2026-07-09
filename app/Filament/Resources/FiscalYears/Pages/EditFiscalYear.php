<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\FiscalYears\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\ERP\Filament\Resources\FiscalYears\Actions\FiscalYearActions;
use Modules\ERP\Filament\Resources\FiscalYears\FiscalYearResource;
use Override;

final class EditFiscalYear extends EditRecord
{
    #[Override]
    protected static string $resource = FiscalYearResource::class;

    /**
     * @return array<int, \Filament\Actions\Action>
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            FiscalYearActions::close(),
            DeleteAction::make(),
        ];
    }
}
