<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Tasks\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Modules\ERP\Filament\Resources\Tasks\TaskResource;
use Override;

final class CreateTask extends CreateRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = TaskResource::class;
}
