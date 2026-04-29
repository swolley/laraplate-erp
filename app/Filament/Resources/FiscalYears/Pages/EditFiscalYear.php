<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\FiscalYears\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\ERP\Filament\Resources\FiscalYears\FiscalYearResource;
use Override;

final class EditFiscalYear extends EditRecord
{
    #[Override]
    protected static string $resource = FiscalYearResource::class;
}
