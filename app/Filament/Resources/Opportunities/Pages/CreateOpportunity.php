<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Opportunities\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\ERP\Filament\Resources\Opportunities\OpportunityResource;
use Override;

final class CreateOpportunity extends CreateRecord
{
    #[Override]
    protected static string $resource = OpportunityResource::class;
}
