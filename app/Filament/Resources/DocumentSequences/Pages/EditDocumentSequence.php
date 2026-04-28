<?php

declare(strict_types=1);

namespace Modules\Business\Filament\Resources\DocumentSequences\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Business\Filament\Resources\DocumentSequences\DocumentSequenceResource;
use Override;

final class EditDocumentSequence extends EditRecord
{
    #[Override]
    protected static string $resource = DocumentSequenceResource::class;
}
