<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PartnerPools\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\ERP\Filament\Resources\PartnerPools\PartnerPoolResource;
use Override;

final class CreatePartnerPool extends CreateRecord
{
    #[Override]
    protected static string $resource = PartnerPoolResource::class;
}
