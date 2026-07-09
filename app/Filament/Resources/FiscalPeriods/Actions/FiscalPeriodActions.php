<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\FiscalPeriods\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Services\Accounting\FiscalPeriodCloser;

final class FiscalPeriodActions
{
    public static function close(): Action
    {
        return Action::make('close_period')
            ->label('Close period')
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('warning')
            ->requiresConfirmation()
            ->authorize(static fn (FiscalPeriod $record): bool => auth()->user()?->can('close', $record) ?? false)
            ->visible(static fn (FiscalPeriod $record): bool => ! $record->is_closed)
            ->action(static function (FiscalPeriod $record): void {
                resolve(FiscalPeriodCloser::class)->closePeriod($record);

                Notification::make()
                    ->title('Fiscal period closed')
                    ->success()
                    ->send();
            });
    }

    public static function reopen(): Action
    {
        return Action::make('reopen_period')
            ->label('Reopen period')
            ->icon(Heroicon::OutlinedLockOpen)
            ->color('gray')
            ->requiresConfirmation()
            ->authorize(static fn (FiscalPeriod $record): bool => auth()->user()?->can('reopen', $record) ?? false)
            ->visible(static fn (FiscalPeriod $record): bool => $record->is_closed)
            ->action(static function (FiscalPeriod $record): void {
                resolve(FiscalPeriodCloser::class)->reopenPeriod($record);

                Notification::make()
                    ->title('Fiscal period reopened')
                    ->success()
                    ->send();
            });
    }
}
