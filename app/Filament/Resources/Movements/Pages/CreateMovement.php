<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Movements\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\ERP\Filament\Resources\Movements\MovementResource;
use Modules\ERP\Models\Movement;
use Modules\ERP\Services\Cash\MovementPostingService;
use Override;

final class CreateMovement extends CreateRecord
{
    #[Override]
    protected static string $resource = MovementResource::class;

    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Movement {
            $movement = Movement::query()->create($data);
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
