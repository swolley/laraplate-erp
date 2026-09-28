<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\DocumentSequences\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Modules\ERP\Filament\Resources\DocumentSequences\Actions\DocumentSequenceActions;
use Modules\ERP\Filament\Resources\DocumentSequences\DocumentSequenceResource;
use Override;

final class EditDocumentSequence extends EditRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = DocumentSequenceResource::class;

    /**
     * @return array<int, \Filament\Actions\Action>
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DocumentSequenceActions::reset(),
        ];
    }
}
