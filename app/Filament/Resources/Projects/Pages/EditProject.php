<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Projects\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\ERP\Filament\Resources\Projects\ProjectResource;
use Override;

final class EditProject extends EditRecord
{
    #[Override]
    protected static string $resource = ProjectResource::class;
}
