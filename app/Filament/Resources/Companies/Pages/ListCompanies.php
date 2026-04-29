<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Companies\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\ERP\Filament\Resources\Companies\CompanyResource;
use Override;

final class ListCompanies extends ListRecords
{
    #[Override]
    protected static string $resource = CompanyResource::class;
}
