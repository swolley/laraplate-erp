<?php

declare(strict_types=1);

namespace Modules\Business\Filament\Resources\Accounts\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Business\Filament\Resources\Accounts\AccountResource;
use Override;

final class ListAccounts extends ListRecords
{
    #[Override]
    protected static string $resource = AccountResource::class;
}
