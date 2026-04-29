<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\DocumentSequences\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\ERP\Filament\Resources\DocumentSequences\DocumentSequenceResource;
use Override;

final class EditDocumentSequence extends EditRecord
{
    #[Override]
    protected static string $resource = DocumentSequenceResource::class;
}
