<?php

declare(strict_types=1);

namespace Modules\Business\Filament\Resources\JournalEntries\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Modules\Business\Models\FiscalPeriod;

final class JournalEntryHeaderFields
{
    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function components(bool $company_locked): array
    {
        return [
            Select::make('company_id')
                ->relationship('company', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->live()
                ->disabled($company_locked),
            Select::make('fiscal_period_id')
                ->label('Fiscal period')
                ->relationship(
                    name: 'fiscal_period',
                    titleAttribute: 'period_no',
                    modifyQueryUsing: static function ($query, ?string $search): void {
                        $query->with('fiscal_year')->orderByDesc('fiscal_year_id')->orderBy('period_no');
                    },
                )
                ->getOptionLabelFromRecordUsing(static function (FiscalPeriod $record): string {
                    $year = $record->fiscal_year->year ?? '?';

                    return "{$year} · period {$record->period_no}";
                })
                ->searchable()
                ->preload()
                ->nullable(),
            Textarea::make('description')
                ->rows(2)
                ->columnSpanFull(),
        ];
    }
}
