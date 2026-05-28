<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\BankStatements\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\ERP\Filament\Resources\BankStatements\BankStatementResource;
use Override;

final class ListBankStatements extends ListRecords
{
    #[Override]
    protected static string $resource = BankStatementResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
