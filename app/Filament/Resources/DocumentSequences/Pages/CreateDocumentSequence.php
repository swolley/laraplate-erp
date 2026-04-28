<?php

declare(strict_types=1);

namespace Modules\Business\Filament\Resources\DocumentSequences\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Business\Filament\Resources\DocumentSequences\DocumentSequenceResource;
use Override;

final class CreateDocumentSequence extends CreateRecord
{
    #[Override]
    protected static string $resource = DocumentSequenceResource::class;
}
