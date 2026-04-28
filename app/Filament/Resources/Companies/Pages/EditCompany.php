<?php

declare(strict_types=1);

namespace Modules\Business\Filament\Resources\Companies\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Business\Filament\Resources\Companies\CompanyResource;
use Override;

final class EditCompany extends EditRecord
{
    #[Override]
    protected static string $resource = CompanyResource::class;
}
