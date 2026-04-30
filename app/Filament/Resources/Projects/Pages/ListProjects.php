<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Projects\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\ERP\Filament\Resources\Projects\ProjectResource;
use Override;

final class ListProjects extends ListRecords
{
    #[Override]
    protected static string $resource = ProjectResource::class;
}
