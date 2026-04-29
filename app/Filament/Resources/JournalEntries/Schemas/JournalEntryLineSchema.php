<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\JournalEntries\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Modules\ERP\Models\Account;
use Modules\ERP\Models\TaxCode;

final class JournalEntryLineSchema
{
    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function forCreateRepeater(): array
    {
        return [
            Select::make('account_id')
                ->label('Account')
                ->required()
                ->searchable()
                ->getSearchResultsUsing(static function (string $search, Get $get): array {
                    $company_id = (int) $get('../../company_id');

                    if ($company_id === 0) {
                        return [];
                    }

                    return Account::query()
                        ->where('company_id', $company_id)
                        ->where(static function (Builder $query) use ($search): void {
                            $query->where('code', 'like', '%' . $search . '%')
                                ->orWhere('name', 'like', '%' . $search . '%');
                        })
                        ->orderBy('code')
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(static fn (Account $account): array => [$account->id => $account->code . ' — ' . $account->name])
                        ->all();
                })
                ->getOptionLabelUsing(static function ($value): ?string {
                    $account = Account::query()->find($value);

                    return $account instanceof Account ? $account->code . ' — ' . $account->name : null;
                }),
            Select::make('tax_code_id')
                ->label('Tax code')
                ->searchable()
                ->nullable()
                ->getSearchResultsUsing(static function (string $search, Get $get): array {
                    $company_id = (int) $get('../../company_id');

                    if ($company_id === 0) {
                        return [];
                    }

                    return TaxCode::query()
                        ->withoutGlobalScopes()
                        ->where('company_id', $company_id)
                        ->where('code', 'like', '%' . $search . '%')
                        ->orderBy('code')
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(static fn (TaxCode $tax_code): array => [$tax_code->id => $tax_code->code])
                        ->all();
                })
                ->getOptionLabelUsing(static function ($value): ?string {
                    $tax_code = TaxCode::query()->withoutGlobalScopes()->find($value);

                    return $tax_code instanceof TaxCode ? $tax_code->code : null;
                }),
            TextInput::make('amount_doc')
                ->numeric()
                ->required(),
            TextInput::make('currency_doc')
                ->length(3)
                ->default('EUR')
                ->required(),
            TextInput::make('amount_local')
                ->numeric()
                ->required(),
            TextInput::make('currency_local')
                ->length(3)
                ->default('EUR')
                ->required(),
            TextInput::make('fx_rate')
                ->numeric()
                ->default(1)
                ->required(),
            TextInput::make('tax_code')
                ->maxLength(32)
                ->nullable(),
            TextInput::make('tax_rate')
                ->numeric()
                ->nullable(),
            TextInput::make('tax_label')
                ->maxLength(255)
                ->nullable(),
            Textarea::make('description')
                ->rows(1)
                ->nullable(),
        ];
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function forEditRelationship(): array
    {
        return [
            Select::make('account_id')
                ->relationship('account', 'code')
                ->searchable()
                ->preload()
                ->required(),
            Select::make('tax_code_id')
                ->relationship('source_tax_code', 'code')
                ->searchable()
                ->preload()
                ->nullable(),
            TextInput::make('amount_doc')
                ->numeric()
                ->required(),
            TextInput::make('currency_doc')
                ->length(3)
                ->default('EUR')
                ->required(),
            TextInput::make('amount_local')
                ->numeric()
                ->required(),
            TextInput::make('currency_local')
                ->length(3)
                ->default('EUR')
                ->required(),
            TextInput::make('fx_rate')
                ->numeric()
                ->default(1)
                ->required(),
            TextInput::make('tax_code')
                ->maxLength(32)
                ->nullable(),
            TextInput::make('tax_rate')
                ->numeric()
                ->nullable(),
            TextInput::make('tax_label')
                ->maxLength(255)
                ->nullable(),
            Textarea::make('description')
                ->rows(1)
                ->nullable(),
        ];
    }
}
