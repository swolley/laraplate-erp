<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Movements\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\ERP\Filament\Resources\Movements\MovementResource;
use Modules\ERP\Models\Movement;
use Modules\ERP\Services\Cash\MovementPostingService;
use Modules\ERP\Support\ConnectionScopedTransaction;
use Override;

final class CreateMovement extends CreateRecord
{
    #[Override]
    protected static string $resource = MovementResource::class;

    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        $movement = new Movement;
        $movement->fill($data);

        return ConnectionScopedTransaction::run($movement, function () use ($movement): Movement {
            $movement->save();
            app(MovementPostingService::class)->post($movement);

            return $movement->fresh() ?? $movement;
        });
    }

    #[Override]
    protected function getRedirectUrl(): string
    {
        return MovementResource::getUrl('view', ['record' => $this->record]);
    }
}
