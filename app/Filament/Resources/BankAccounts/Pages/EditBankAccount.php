<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\BankAccounts\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Modules\ERP\Filament\Resources\BankAccounts\BankAccountResource;
use Override;

final class EditBankAccount extends EditRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = BankAccountResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
