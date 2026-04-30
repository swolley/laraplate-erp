<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Leads\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\ERP\Filament\Resources\Leads\LeadResource;
use Override;

final class CreateLead extends CreateRecord
{
    #[Override]
    protected static string $resource = LeadResource::class;
}
