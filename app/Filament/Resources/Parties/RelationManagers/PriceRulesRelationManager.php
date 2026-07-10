<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Parties\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\ERP\Casts\DiscountType;

final class PriceRulesRelationManager extends RelationManager
{
    protected static string $relationship = 'price_rules';

    public function form(Schema $schema): Schema
    {
        $discount_type_options = collect(DiscountType::cases())
            ->mapWithKeys(static fn (DiscountType $type): array => [$type->value => $type->name])
            ->all();

        return $schema
            ->components([
                Select::make('item_id')
                    ->relationship('item', 'name')
                    ->searchable()
                    ->preload()
                    ->rules(['nullable', 'required_without:taxonomy_id', 'prohibits:taxonomy_id']),
                Select::make('taxonomy_id')
                    ->label('Activity')
                    ->relationship('activity', 'name')
                    ->searchable()
                    ->preload()
                    ->rules(['nullable', 'required_without:item_id', 'prohibits:item_id']),
                Select::make('discount_type')
                    ->options($discount_type_options)
                    ->required(),
                TextInput::make('discount_value')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                TextInput::make('priority')
                    ->integer()
                    ->minValue(0)
                    ->default(0),
                DatePicker::make('valid_from'),
                DatePicker::make('valid_to'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('item.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('activity.name')
                    ->label('Activity')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('discount_type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('discount_value')
                    ->sortable(),
                TextColumn::make('priority')
                    ->sortable(),
                TextColumn::make('valid_from')
                    ->date()
                    ->sortable(),
                TextColumn::make('valid_to')
                    ->date()
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        $data['company_id'] = $this->getOwnerRecord()->company_id;

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
