<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\BankAccounts\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\ERP\Filament\Resources\BankAccounts\BankAccountResource;
use Override;

final class CreateBankAccount extends CreateRecord
{
    #[Override]
    protected static string $resource = BankAccountResource::class;
}
