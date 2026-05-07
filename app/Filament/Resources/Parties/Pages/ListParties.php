<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Parties\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\ERP\Filament\Resources\Parties\PartyResource;
use Override;

final class ListParties extends ListRecords
{
    #[Override]
    protected static string $resource = PartyResource::class;
}
