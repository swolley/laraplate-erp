<?php

declare(strict_types=1);

namespace Modules\Business\Filament\Resources\Accounts\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Business\Filament\Resources\Accounts\AccountResource;
use Override;

final class EditAccount extends EditRecord
{
    #[Override]
    protected static string $resource = AccountResource::class;
}
