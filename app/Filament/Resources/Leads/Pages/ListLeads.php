<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Leads\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Utils\HasRecords;
use Modules\ERP\Filament\Resources\Leads\LeadResource;
use Override;

final class ListLeads extends ListRecords
{
    use HasRecords;

    #[Override]
    protected static string $resource = LeadResource::class;
}
