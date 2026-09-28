<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\FiscalYears\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Modules\ERP\Filament\Resources\FiscalYears\FiscalYearResource;
use Override;

final class CreateFiscalYear extends CreateRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = FiscalYearResource::class;
}
