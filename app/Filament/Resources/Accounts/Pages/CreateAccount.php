<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Accounts\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Modules\ERP\Filament\Resources\Accounts\AccountResource;
use Override;

final class CreateAccount extends CreateRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = AccountResource::class;
}
