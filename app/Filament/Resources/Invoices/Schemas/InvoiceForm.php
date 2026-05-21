<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\Invoices\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Models\DeliveryNoteLine;
use Modules\ERP\Models\Invoice;

final class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        $direction_options = collect(InvoiceDirection::cases())
            ->mapWithKeys(static fn (InvoiceDirection $direction): array => [$direction->value => $direction->value])
            ->all();

        return $schema
            ->components([
                Select::make('company_id')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('direction')
                    ->options($direction_options)
                    ->required()
                    ->live()
                    ->disabled(static fn (?Invoice $record): bool => $record?->journal_entry_id !== null),
                Select::make('invoice_type')
                    ->options(collect(InvoiceType::cases())
                        ->mapWithKeys(static fn (InvoiceType $type): array => [$type->value => $type->value])
                        ->all())
                    ->required()
                    ->default(InvoiceType::Invoice->value)
                    ->live(),
                Select::make('credited_invoice_id')
                    ->label('Credited Invoice')
                    ->relationship('credited_invoice', 'reference')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->visible(static fn (Get $get): bool => in_array($get('invoice_type'), [
                        InvoiceType::CreditNote->value,
                        InvoiceType::DebitNote->value,
                    ], true)),
                TextInput::make('currency')
                    ->length(3)
                    ->required()
                    ->default('EUR'),
                TextInput::make('reference')
                    ->disabled()
                    ->helperText('Assigned automatically at posting time'),
                Placeholder::make('posted_at_display')
                    ->label('Posted at')
                    ->content(static fn (?Invoice $record): string => $record?->posted_at?->format('Y-m-d H:i:s') ?? 'Draft')
                    ->visible(static fn (?Invoice $record): bool => $record !== null),
                Repeater::make('line_items')
                    ->disabled(static fn (?Invoice $record): bool => $record?->journal_entry_id !== null)
                    ->schema([
                        Select::make('sales_order_line_id')
                            ->label('Sales order line')
                            ->relationship('sales_order_line', 'id')
                            ->searchable()
                            ->preload()
                            ->visible(static fn (Get $get): bool => InvoiceDirection::Sale->value === $get('../../direction')),
                        Select::make('purchase_order_line_id')
                            ->label('Purchase order line')
                            ->relationship('purchase_order_line', 'id')
                            ->searchable()
                            ->preload()
                            ->visible(static fn (Get $get): bool => InvoiceDirection::Purchase->value === $get('../../direction')),
                        Select::make('goods_receipt_line_id')
                            ->label('Goods receipt line')
                            ->relationship('goods_receipt_line', 'id')
                            ->searchable()
                            ->preload()
                            ->visible(static fn (Get $get): bool => InvoiceDirection::Purchase->value === $get('../../direction')),
                        TextInput::make('match_status')
                            ->label('Match status')
                            ->disabled()
                            ->visible(static fn (Get $get): bool => InvoiceDirection::Purchase->value === $get('../../direction')
                                && filled($get('match_status'))),
                        TextInput::make('description')
                            ->nullable(),
                        TextInput::make('quantity')
                            ->numeric()
                            ->minValue(0.0001)
                            ->default(1)
                            ->required(),
                        TextInput::make('unit_price')
                            ->numeric()
                            ->required(),
                        Select::make('tax_code_id')
                            ->relationship('applied_tax_code', 'code')
                            ->searchable()
                            ->preload(),
                        Repeater::make('delivery_note_line_links')
                            ->label('Delivery note lines')
                            ->schema([
                                Select::make('delivery_note_line_id')
                                    ->label('DDT line')
                                    ->searchable()
                                    ->required()
                                    ->getSearchResultsUsing(static function (string $search, Get $get): array {
                                        $company_id = $get('../../company_id');

                                        if ($company_id === null) {
                                            return [];
                                        }

                                        return DeliveryNoteLine::query()
                                            ->where('company_id', (int) $company_id)
                                            ->whereHas('delivery_note', static fn ($query) => $query->whereNotNull('posted_at'))
                                            ->when(
                                                $search !== '',
                                                static fn ($query) => $query->where('id', 'like', '%' . $search . '%'),
                                            )
                                            ->orderByDesc('id')
                                            ->limit(25)
                                            ->get()
                                            ->mapWithKeys(static fn (DeliveryNoteLine $line): array => [
                                                $line->id => 'DDT line #' . $line->id . ' (qty ' . $line->quantity . ')',
                                            ])
                                            ->all();
                                    })
                                    ->getOptionLabelUsing(static fn ($value): ?string => $value === null
                                        ? null
                                        : 'DDT line #' . $value),
                                TextInput::make('quantity')
                                    ->numeric()
                                    ->minValue(1)
                                    ->required(),
                            ])
                            ->defaultItems(0)
                            ->columnSpanFull()
                            ->visible(static fn (Get $get): bool => InvoiceDirection::Sale->value === $get('../../direction')),
                    ])
                    ->defaultItems(1)
                    ->minItems(1)
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->nullable()
                    ->columnSpanFull(),
            ]);
    }
}
