<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\BankAccounts\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\ERP\Filament\Resources\BankAccounts\BankAccountResource;
use Override;

final class ListBankAccounts extends ListRecords
{
    #[Override]
    protected static string $resource = BankAccountResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
