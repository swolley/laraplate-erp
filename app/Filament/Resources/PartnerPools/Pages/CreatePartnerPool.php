<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PartnerPools\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Modules\ERP\Filament\Resources\PartnerPools\PartnerPoolResource;
use Override;

final class CreatePartnerPool extends CreateRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = PartnerPoolResource::class;
}
