<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Invoices\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Filament\Resources\Invoices\Actions\InvoicePostingActions;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\InvoiceLine;

final class InvoicesTable
{
    use HasTable;

    public static function configure(Table $table): Table
    {
        return self::configureTable(
            table: $table,
            columns: static function (Collection $default_columns): void {
                $default_columns->unshift(...[
                    TextColumn::make('company.name')
                        ->label('Company')
                        ->toggleable(),
                    TextColumn::make('reference')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('direction')
                        ->badge()
                        ->sortable(),
                    TextColumn::make('invoice_type')
                        ->badge()
                        ->sortable(),
                    TextColumn::make('currency')
                        ->sortable(),
                    TextColumn::make('posted_at')
                        ->dateTime()
                        ->sortable()
                        ->placeholder('Draft'),
                    TextColumn::make('lines.match_status')
                        ->label('3-way match')
                        ->badge()
                        ->toggleable()
                        ->visible(static fn (): bool => true)
                        ->state(static function (Invoice $record): ?string {
                            if ($record->direction !== InvoiceDirection::Purchase) {
                                return null;
                            }

                            $statuses = $record->lines()
                                ->whereNotNull('match_status')
                                ->get()
                                ->map(static fn (InvoiceLine $line): string => $line->match_status?->value ?? '')
                                ->filter()
                                ->unique()
                                ->values()
                                ->all();

                            return $statuses === [] ? null : implode(', ', $statuses);
                        }),
                ]);
            },
            actions: static function (Collection $default_actions): void {
                $default_actions->prepend(
                    InvoicePostingActions::post(),
                    InvoicePostingActions::unpost(),
                );
            },
        );
    }
}
