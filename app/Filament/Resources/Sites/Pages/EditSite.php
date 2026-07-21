<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Sites\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\ERP\Filament\Resources\Sites\SiteResource;
use Override;

final class EditSite extends EditRecord
{
    #[Override] protected static string $resource = SiteResource::class;
    #[Override] protected function getHeaderActions(): array { return [DeleteAction::make()]; }
}
