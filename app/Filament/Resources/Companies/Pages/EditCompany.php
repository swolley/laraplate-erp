<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Companies\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\ERP\Filament\Resources\Companies\CompanyResource;
use Override;

final class EditCompany extends EditRecord
{
    #[Override]
    protected static string $resource = CompanyResource::class;
}
