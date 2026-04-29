<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\JournalEntries\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

final class JournalEntryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('company.name')
                    ->label('Company'),
                TextEntry::make('fiscal_period_id')
                    ->label('Fiscal period id')
                    ->placeholder('—'),
                TextEntry::make('posted_at')
                    ->dateTime()
                    ->placeholder('—'),
                TextEntry::make('posted_by')
                    ->label('Posted by (user id)')
                    ->placeholder('—'),
                TextEntry::make('description')
                    ->placeholder('—')
                    ->columnSpanFull(),
                RepeatableEntry::make('lines')
                    ->label('Lines')
                    ->schema([
                        TextEntry::make('line_no')->label('#'),
                        TextEntry::make('account.code')->label('Account'),
                        TextEntry::make('amount_local')->label('Amount (local)'),
                        TextEntry::make('currency_local'),
                        TextEntry::make('description')->placeholder('—'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
