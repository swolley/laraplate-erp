<?php

declare(strict_types=1);

namespace Modules\Business\Filament\Resources\DocumentSequences\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Business\Filament\Resources\DocumentSequences\DocumentSequenceResource;
use Override;

final class ListDocumentSequences extends ListRecords
{
    #[Override]
    protected static string $resource = DocumentSequenceResource::class;
}
