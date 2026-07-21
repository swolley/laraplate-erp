<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Movements\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\ERP\Filament\Resources\Movements\MovementResource;
use Override;

final class ListMovements extends ListRecords
{
    #[Override]
    protected static string $resource = MovementResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
