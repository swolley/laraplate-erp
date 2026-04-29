<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Companies\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\ERP\Filament\Resources\Companies\CompanyResource;
use Override;

final class CreateCompany extends CreateRecord
{
    #[Override]
    protected static string $resource = CompanyResource::class;
}
