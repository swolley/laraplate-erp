<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Movements\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

final class MovementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('company.name')->label('Company'),
            TextEntry::make('type'),
            TextEntry::make('occurred_on')->date(),
            TextEntry::make('counterparty_account.code')->label('Counterparty account'),
            TextEntry::make('amount_doc')->numeric(4),
            TextEntry::make('currency_doc'),
            TextEntry::make('amount_local')->numeric(4),
            TextEntry::make('currency_local'),
            TextEntry::make('fx_rate')->numeric(8),
            TextEntry::make('posted_journal_entry_id')->label('Journal entry'),
            TextEntry::make('description')->columnSpanFull()->placeholder('—'),
        ]);
    }
}
