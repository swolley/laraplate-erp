<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\BankStatements\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\ERP\Filament\Resources\BankStatements\BankStatementResource;
use Override;

final class EditBankStatement extends EditRecord
{
    #[Override]
    protected static string $resource = BankStatementResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
