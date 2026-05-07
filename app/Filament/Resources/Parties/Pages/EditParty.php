<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Parties\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\ERP\Filament\Resources\Parties\PartyResource;
use Override;

final class EditParty extends EditRecord
{
    #[Override]
    protected static string $resource = PartyResource::class;
}
