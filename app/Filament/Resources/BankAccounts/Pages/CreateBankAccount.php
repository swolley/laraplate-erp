<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\BankAccounts\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Modules\ERP\Filament\Resources\BankAccounts\BankAccountResource;
use Override;

final class CreateBankAccount extends CreateRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = BankAccountResource::class;
}
