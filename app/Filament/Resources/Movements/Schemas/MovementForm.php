<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Movements\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Modules\ERP\Casts\AccountKind;
use Modules\ERP\Casts\MovementType;
use Modules\ERP\Models\Account;

final class MovementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('company_id')
                ->relationship('company', 'name')
                ->searchable()
                ->preload()
                ->required(),
            Select::make('type')
                ->options(collect(MovementType::cases())->mapWithKeys(
                    static fn (MovementType $type): array => [$type->value => ucfirst($type->value)],
                )->all())
                ->required()
                ->live(),
            DatePicker::make('occurred_on')
                ->required()
                ->default(now()),
            TextInput::make('amount_doc')
                ->label('Amount')
                ->numeric()
                ->minValue(0.0001)
                ->required(),
            TextInput::make('currency_doc')
                ->label('Currency')
                ->length(3)
                ->default('EUR')
                ->required(),
            Select::make('counterparty_account_id')
                ->label('Revenue / expense account')
                ->options(fn (Get $get): array => Account::query()
                    ->when($get('company_id'), static fn ($query, mixed $company_id) => $query->where('company_id', $company_id))
                    ->where('kind', $get('type') === MovementType::Income->value
                        ? AccountKind::Revenue->value
                        : AccountKind::Expense->value)
                    ->where('is_active', true)
                    ->orderBy('code')
                    ->get()
                    ->mapWithKeys(static fn (Account $account): array => [(int) $account->id => $account->code . ' - ' . $account->name])
                    ->all())
                ->searchable()
                ->required(),
            Textarea::make('description')
                ->nullable()
                ->columnSpanFull(),
        ]);
    }
}
