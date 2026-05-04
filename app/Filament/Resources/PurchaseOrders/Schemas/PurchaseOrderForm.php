<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\PurchaseOrders\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Modules\ERP\Models\Item;

final class PurchaseOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        $status_options = [
            'draft' => 'draft',
            'confirmed' => 'confirmed',
            'partial' => 'partial',
            'received' => 'received',
        ];

        return $schema
            ->components([
                Select::make('company_id')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->disabledOn('edit'),
                Select::make('customer_id')
                    ->relationship(
                        'customer',
                        'name',
                        modifyQueryUsing: static function (Builder $query, ?string $search, Get $get): Builder {
                            $company_id = (int) ($get('company_id') ?? 0);

                            if ($company_id === 0) {
                                return $query->whereRaw('0 = 1');
                            }

                            return $query->where('customers.company_id', $company_id);
                        },
                    )
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('reference')
                    ->maxLength(64)
                    ->nullable(),
                TextInput::make('currency')
                    ->length(3)
                    ->required()
                    ->default('EUR'),
                Select::make('status')
                    ->options($status_options)
                    ->required()
                    ->default('draft'),
                DateTimePicker::make('ordered_at')
                    ->nullable(),
                Repeater::make('line_items')
                    ->schema([
                        Select::make('item_id')
                            ->label('Item')
                            ->searchable()
                            ->nullable()
                            ->getSearchResultsUsing(static function (string $search, Get $get): array {
                                $company_id = (int) $get('../../company_id');

                                if ($company_id === 0) {
                                    return [];
                                }

                                return Item::query()
                                    ->where('company_id', $company_id)
                                    ->where(static function (Builder $query) use ($search): void {
                                        $query->where('name', 'like', '%' . $search . '%')
                                            ->orWhere('sku', 'like', '%' . $search . '%');
                                    })
                                    ->orderBy('name')
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(static fn (Item $item): array => [
                                        $item->id => $item->name . ' (' . $item->sku . ')',
                                    ])
                                    ->all();
                            })
                            ->getOptionLabelUsing(static function (?int $value): ?string {
                                if ($value === null) {
                                    return null;
                                }

                                $item = Item::query()->find($value);

                                return $item instanceof Item ? $item->name . ' (' . $item->sku . ')' : null;
                            }),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('qty_ordered')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->minValue(1),
                        TextInput::make('qty_received')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->required(),
                        TextInput::make('unit_price')
                            ->numeric()
                            ->nullable(),
                    ])
                    ->defaultItems(1)
                    ->minItems(1)
                    ->addActionLabel('Add line')
                    ->columnSpanFull(),
            ]);
    }
}
