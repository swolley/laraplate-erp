<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\JournalEntries\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Modules\ERP\Filament\Resources\JournalEntries\JournalEntryResource;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Services\Accounting\JournalPostingService;

final class JournalEntryActions
{
    public static function reverse(): Action
    {
        return Action::make('reverse')
            ->label('Reverse')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->requiresConfirmation()
            ->authorize(static fn (JournalEntry $record): bool => auth()->user()?->can('reverse', $record) ?? false)
            ->visible(static fn (JournalEntry $record): bool => $record->posted_at !== null
                && ! $record->reversal_voucher()->exists())
            ->form([
                Textarea::make('reversal_reason')
                    ->label('Reversal reason')
                    ->required()
                    ->maxLength(500),
            ])
            ->action(static function (JournalEntry $record, array $data): void {
                $user_id = auth()->id();

                $reversal = resolve(JournalPostingService::class)->reverse(
                    $record,
                    // company_id is required on a journal entry, so the relation is
                    // there; firstOrFail says so in the type and fails loudly rather
                    // than passing null into a Company parameter.
                    $record->company()->firstOrFail(),
                    (string) $data['reversal_reason'],
                    // auth()->id() is int|string|null: a string key belongs to a user
                    // model this application does not have, so narrowing is honest.
                    is_numeric($user_id) ? (int) $user_id : null,
                );

                Notification::make()
                    ->title('Journal reversed')
                    ->body('Reversal voucher #' . $reversal->id)
                    ->success()
                    ->send();
            })
            ->successRedirectUrl(static fn (JournalEntry $record): string => JournalEntryResource::getUrl('view', ['record' => $record]));
    }
}
