<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PartnerPools\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\ERP\Filament\Resources\PartnerPools\PartnerPoolResource;
use Override;

final class ListPartnerPools extends ListRecords
{
    #[Override]
    protected static string $resource = PartnerPoolResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
