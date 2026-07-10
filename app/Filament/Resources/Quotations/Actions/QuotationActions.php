<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Quotations\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Modules\ERP\Models\Quotation;

final class QuotationActions
{
    public static function unlock(): Action
    {
        return Action::make('unlock')
            ->label('Unlock')
            ->icon(Heroicon::OutlinedLockOpen)
            ->color('warning')
            ->requiresConfirmation()
            ->authorize(static fn (Quotation $record): bool => auth()->user()?->can('unlock', $record) ?? false)
            ->visible(static fn (Quotation $record): bool => $record->isLocked())
            ->action(static function (Quotation $record): void {
                $record->unlock();

                Notification::make()
                    ->title('Quotation unlocked')
                    ->success()
                    ->send();
            });
    }
}
