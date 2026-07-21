<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Sites\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\ERP\Filament\Resources\Sites\SiteResource;
use Override;

final class CreateSite extends CreateRecord
{
    #[Override] protected static string $resource = SiteResource::class;
}
