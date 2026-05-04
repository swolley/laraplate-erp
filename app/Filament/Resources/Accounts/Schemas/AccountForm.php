<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Accounts\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Modules\ERP\Casts\AccountKind;
use Illuminate\Database\Query\Builder;

final class AccountForm
{
    public static function configure(Schema $schema): Schema
    {
        $kind_options = collect(AccountKind::cases())
            ->mapWithKeys(static fn (AccountKind $kind): array => [$kind->value => $kind->value])
            ->all();

        return $schema
            ->components([
                Select::make('company_id')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabledOn('edit'),
                TextInput::make('code')
                    ->required()
                    ->maxLength(32),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Select::make('kind')
                    ->options($kind_options)
                    ->required(),
                Select::make('parent_id')
                    ->relationship(
                        name: 'parent',
                        titleAttribute: 'code',
                        modifyQueryUsing: static function (Builder $query, ?string $search): Builder {
                            return $query->orderBy('code');
                        },
                    )
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Toggle::make('is_active')
                    ->default(true),
                KeyValue::make('meta')
                    ->keyLabel('Key')
                    ->valueLabel('Value')
                    ->addActionLabel('Add meta entry')
                    ->nullable(),
            ]);
    }
}
