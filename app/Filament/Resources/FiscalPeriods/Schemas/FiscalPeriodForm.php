<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\FiscalPeriods\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Query\Builder;
use Modules\ERP\Models\FiscalYear;

final class FiscalPeriodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('fiscal_year_id')
                    ->label('Fiscal year')
                    ->relationship(
                        name: 'fiscal_year',
                        titleAttribute: 'year',
                        modifyQueryUsing: static function (Builder $query, ?string $search): Builder {
                            return $query->with('company')->orderBy('year', 'desc');
                        },
                    )
                    ->getOptionLabelFromRecordUsing(static function (FiscalYear $record): string {
                        $company = $record->company->name ?? '—';

                        return "{$record->year} ({$company})";
                    })
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('period_no')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(366),
                DatePicker::make('start_date')
                    ->required()
                    ->native(false),
                DatePicker::make('end_date')
                    ->required()
                    ->native(false),
                Toggle::make('is_closed')
                    ->label('Closed'),
            ]);
    }
}
