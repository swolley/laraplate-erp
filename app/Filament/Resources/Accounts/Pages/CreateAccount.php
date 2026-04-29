<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Accounts\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\ERP\Filament\Resources\Accounts\AccountResource;
use Override;

final class CreateAccount extends CreateRecord
{
    #[Override]
    protected static string $resource = AccountResource::class;
}
