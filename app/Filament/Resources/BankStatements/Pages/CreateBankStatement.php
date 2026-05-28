<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\BankStatements\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\ERP\Filament\Resources\BankStatements\BankStatementResource;
use Override;

final class CreateBankStatement extends CreateRecord
{
    #[Override]
    protected static string $resource = BankStatementResource::class;
}
