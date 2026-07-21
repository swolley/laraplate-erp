<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Sites\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\ERP\Filament\Resources\Sites\SiteResource;
use Override;

final class ListSites extends ListRecords
{
    #[Override] protected static string $resource = SiteResource::class;
    #[Override] protected function getHeaderActions(): array { return [CreateAction::make()]; }
}
