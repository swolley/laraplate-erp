<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Projects\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Modules\ERP\Filament\Resources\Projects\ProjectResource;
use Override;

final class CreateProject extends CreateRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = ProjectResource::class;
}
