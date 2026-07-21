<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Quotations\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Modules\ERP\Models\Quotation;
use Modules\ERP\Filament\Resources\Quotations\QuotationResource;
use Modules\ERP\Services\Quotations\QuotationRevisionService;

final class QuotationActions
{
    public static function createRevision(): Action
    {
        return Action::make('create_revision')
            ->label('Create revision')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->requiresConfirmation()
            ->authorize(static fn (Quotation $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->visible(static fn (Quotation $record): bool => ($record->isLocked() || $record->status->value !== 'draft') && ! $record->revision()->exists())
            ->action(static function (Quotation $record, Action $action): void {
                $revision = app(QuotationRevisionService::class)->createRevision($record);

                Notification::make()->title('Quotation revision created')->success()->send();
                $action->redirect(QuotationResource::getUrl('edit', ['record' => $revision]));
            });
    }

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
