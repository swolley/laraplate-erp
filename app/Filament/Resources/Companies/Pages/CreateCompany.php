<?php

declare(strict_types=1);

namespace Modules\Business\Filament\Resources\Companies\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Business\Filament\Resources\Companies\CompanyResource;
use Override;

final class CreateCompany extends CreateRecord
{
    #[Override]
    protected static string $resource = CompanyResource::class;
}
