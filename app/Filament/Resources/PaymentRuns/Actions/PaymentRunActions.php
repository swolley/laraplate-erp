<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PaymentRuns\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Modules\ERP\Casts\PaymentRunStatus;
use Modules\ERP\Models\PaymentRun;
use Modules\ERP\Services\Payments\CbiBonificiExporter;
use Modules\ERP\Services\Payments\SepaPain001Exporter;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PaymentRunActions
{
    public static function approve(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->visible(static fn (PaymentRun $record): bool => $record->status === PaymentRunStatus::Draft)
            ->action(static function (PaymentRun $record): void {
                $record->status = PaymentRunStatus::Approved;
                $record->approved_at = now();
                $record->save();

                Notification::make()
                    ->title('Payment run approved')
                    ->success()
                    ->send();
            });
    }

    public static function exportSepa(): Action
    {
        return Action::make('export_sepa')
            ->label('Export SEPA')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('primary')
            ->visible(static fn (PaymentRun $record): bool => $record->status === PaymentRunStatus::Approved)
            ->action(static function (PaymentRun $record): StreamedResponse {
                $xml = app(SepaPain001Exporter::class)->export($record);
                $file_name = $record->fresh()?->export_file_name ?? 'payment-run-pain001.xml';

                return response()->streamDownload(static function () use ($xml): void {
                    echo $xml;
                }, $file_name, [
                    'Content-Type' => 'application/xml; charset=UTF-8',
                ]);
            });
    }


    public static function exportCbiBonifici(): Action
    {
        return Action::make('export_cbi_bonifici')
            ->label('Export CBI')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->visible(static fn (PaymentRun $record): bool => $record->status === PaymentRunStatus::Approved)
            ->action(static function (PaymentRun $record): StreamedResponse {
                $content = app(CbiBonificiExporter::class)->export($record);
                $file_name = $record->fresh()?->export_file_name ?? 'payment-run-cbi-bonifici.txt';

                return response()->streamDownload(static function () use ($content): void {
                    echo $content;
                }, $file_name, [
                    'Content-Type' => 'text/plain; charset=UTF-8',
                ]);
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('Cancel')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->visible(static fn (PaymentRun $record): bool => in_array($record->status, [
                PaymentRunStatus::Draft,
                PaymentRunStatus::Approved,
            ], true))
            ->action(static function (PaymentRun $record): void {
                $record->status = PaymentRunStatus::Cancelled;
                $record->save();

                $record->lines()->update(['status' => \Modules\ERP\Casts\PaymentRunLineStatus::Cancelled->value]);

                Notification::make()
                    ->title('Payment run cancelled')
                    ->success()
                    ->send();
            });
    }
}
