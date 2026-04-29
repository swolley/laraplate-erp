<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\FiscalYears\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\ERP\Filament\Resources\FiscalYears\FiscalYearResource;
use Override;

final class ListFiscalYears extends ListRecords
{
    #[Override]
    protected static string $resource = FiscalYearResource::class;
}
