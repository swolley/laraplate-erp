<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\DeliveryNotes\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Modules\ERP\Models\DeliveryNote;

final class DeliveryNotePostingActions
{
    public static function post(): Action
    {
        return Action::make('post')
            ->label('Post inventory')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->authorize(static fn (DeliveryNote $record): bool => auth()->user()?->can('post', $record) ?? false)
            ->visible(static fn (DeliveryNote $record): bool => $record->posted_at === null)
            ->action(static function (DeliveryNote $record): void {
                $record->update(['posted_at' => now()]);

                Notification::make()
                    ->title('Delivery note posted')
                    ->success()
                    ->send();
            });
    }

    public static function unpost(): Action
    {
        return Action::make('unpost')
            ->label('Unpost inventory')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->authorize(static fn (DeliveryNote $record): bool => auth()->user()?->can('unpost', $record) ?? false)
            ->visible(static fn (DeliveryNote $record): bool => $record->posted_at !== null)
            ->action(static function (DeliveryNote $record): void {
                $record->update(['posted_at' => null]);

                Notification::make()
                    ->title('Delivery note unposted')
                    ->success()
                    ->send();
            });
    }
}
