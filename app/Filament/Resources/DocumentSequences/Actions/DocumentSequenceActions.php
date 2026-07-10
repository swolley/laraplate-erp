<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\DocumentSequences\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Modules\ERP\Models\DocumentSequence;
use Modules\ERP\Services\Accounting\DocumentSequenceResetService;

final class DocumentSequenceActions
{
    public static function reset(): Action
    {
        return Action::make('reset')
            ->label('Reset counter')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Resetting a document sequence changes the next allocated number. Use this only for controlled administrative corrections.')
            ->schema([
                TextInput::make('last_number')
                    ->label('Last number')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required(),
            ])
            ->authorize(static fn (DocumentSequence $record): bool => auth()->user()?->can('reset', $record) ?? false)
            ->action(static function (DocumentSequence $record, array $data): void {
                resolve(DocumentSequenceResetService::class)->reset($record, (int) $data['last_number']);

                Notification::make()
                    ->title('Document sequence reset')
                    ->success()
                    ->send();
            });
    }
}
