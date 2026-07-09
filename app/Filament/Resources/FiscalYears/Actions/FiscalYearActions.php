<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\FiscalYears\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Services\Accounting\FiscalPeriodCloser;

final class FiscalYearActions
{
    public static function close(): Action
    {
        return Action::make('close_year')
            ->label('Close fiscal year')
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Closes all open periods in this year and locks the fiscal year.')
            ->authorize(static fn (FiscalYear $record): bool => auth()->user()?->can('close', $record) ?? false)
            ->visible(static fn (FiscalYear $record): bool => ! $record->is_closed)
            ->action(static function (FiscalYear $record): void {
                resolve(FiscalPeriodCloser::class)->closeYear($record);

                Notification::make()
                    ->title('Fiscal year closed')
                    ->success()
                    ->send();
            });
    }
}
