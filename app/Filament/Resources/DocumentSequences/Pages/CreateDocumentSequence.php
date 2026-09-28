<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\DocumentSequences\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Modules\ERP\Filament\Resources\DocumentSequences\DocumentSequenceResource;
use Override;

final class CreateDocumentSequence extends CreateRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = DocumentSequenceResource::class;
}
