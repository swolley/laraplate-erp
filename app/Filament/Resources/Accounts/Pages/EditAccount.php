<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Accounts\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Modules\ERP\Filament\Resources\Accounts\AccountResource;
use Override;

final class EditAccount extends EditRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = AccountResource::class;
}
