<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Opportunities\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Modules\ERP\Filament\Resources\Opportunities\OpportunityResource;
use Override;

final class EditOpportunity extends EditRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = OpportunityResource::class;
}
