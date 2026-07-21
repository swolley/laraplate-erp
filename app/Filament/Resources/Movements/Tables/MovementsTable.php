<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Movements\Tables;

use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;
use Modules\ERP\Filament\Resources\Movements\MovementResource;
use Modules\ERP\Models\Movement;

final class MovementsTable
{
    use HasTable;

    public static function configure(Table $table): Table
    {
        return self::configureTable(
            table: $table,
            columns: static function (Collection $default_columns): void {
                $default_columns->unshift(...[
                    TextColumn::make('occurred_on')->date()->sortable(),
                    TextColumn::make('type')->badge()->sortable(),
                    TextColumn::make('counterparty_account.code')->label('Account')->searchable()->sortable(),
                    TextColumn::make('description')->limit(40)->searchable(),
                    TextColumn::make('amount_doc')->label('Amount')->numeric(4)->sortable(),
                    TextColumn::make('currency_doc')->label('Currency'),
                    TextColumn::make('amount_local')->label('Local amount')->numeric(4)->toggleable(),
                    TextColumn::make('posted_journal_entry_id')->label('Journal')->sortable(),
                ]);
            },
            actions: static function (Collection $default_actions): void {
                $default_actions->prepend(
                    Action::make('details')
                        ->label('Details')
                        ->url(static fn (Movement $record): string => MovementResource::getUrl('view', ['record' => $record])),
                );
            },
        );
    }
}
