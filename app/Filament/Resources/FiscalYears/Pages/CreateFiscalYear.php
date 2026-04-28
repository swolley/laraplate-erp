<?php

declare(strict_types=1);

namespace Modules\Business\Filament\Resources\FiscalYears\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Business\Filament\Resources\FiscalYears\FiscalYearResource;
use Override;

final class CreateFiscalYear extends CreateRecord
{
    #[Override]
    protected static string $resource = FiscalYearResource::class;
}
