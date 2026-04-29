<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\JournalEntries\Tables;

use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\ERP\Filament\Resources\JournalEntries\JournalEntryResource;
use Modules\ERP\Models\JournalEntry;
use Modules\Core\Filament\Utils\HasTable;

final class JournalEntriesTable
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
                    TextColumn::make('id')
                        ->sortable(),
                    TextColumn::make('posted_at')
                        ->dateTime()
                        ->sortable()
                        ->placeholder('Draft'),
                    IconColumn::make('is_posted')
                        ->label('Posted')
                        ->boolean()
                        ->state(static fn (JournalEntry $record): bool => $record->posted_at !== null),
                    TextColumn::make('description')
                        ->limit(40)
                        ->toggleable(),
                ]);
            },
            actions: static function (Collection $default_actions): void {
                $default_actions->prepend(
                    Action::make('fullPageView')
                        ->label('Details')
                        ->url(static fn (JournalEntry $record): string => JournalEntryResource::getUrl('view', ['record' => $record])),
                );
            },
        );
    }
}
