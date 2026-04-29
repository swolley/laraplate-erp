<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\DocumentSequences\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\ERP\Filament\Resources\DocumentSequences\DocumentSequenceResource;
use Override;

final class CreateDocumentSequence extends CreateRecord
{
    #[Override]
    protected static string $resource = DocumentSequenceResource::class;
}
