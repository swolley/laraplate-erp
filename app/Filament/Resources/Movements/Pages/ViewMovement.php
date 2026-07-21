<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Movements\Pages;

use Filament\Resources\Pages\ViewRecord;
use Modules\ERP\Filament\Resources\Movements\MovementResource;
use Override;

final class ViewMovement extends ViewRecord
{
    #[Override]
    protected static string $resource = MovementResource::class;
}
