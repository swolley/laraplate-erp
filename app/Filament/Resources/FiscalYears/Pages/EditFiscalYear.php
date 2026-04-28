<?php

declare(strict_types=1);

namespace Modules\Business\Filament\Resources\FiscalYears\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Business\Filament\Resources\FiscalYears\FiscalYearResource;
use Override;

final class EditFiscalYear extends EditRecord
{
    #[Override]
    protected static string $resource = FiscalYearResource::class;
}
