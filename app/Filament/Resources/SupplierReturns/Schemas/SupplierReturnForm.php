<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Resources\SupplierReturns\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Modules\ERP\Casts\InvoiceDirection;
use Modules\ERP\Casts\InvoiceType;
use Modules\ERP\Casts\ReturnStatus;
use Modules\ERP\Models\InvoiceLine;
use Modules\ERP\Models\PurchaseOrderLine;

final class SupplierReturnForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('company_id')
                    ->relationship('company', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabledOn('edit'),
                Select::make('party_id')
                    ->relationship(
                        'party',
                        'name',
                        modifyQueryUsing: static fn (Builder $query): Builder => $query->suppliers(),
                    )
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('purchase_order_id')
                    ->relationship('purchase_order', 'reference')
                    ->searchable()
                    ->preload()
                    ->live(),
                Select::make('debit_note_invoice_id')
                    ->relationship('debit_note_invoice', 'reference')
                    ->searchable()
                    ->preload()
                    ->disabled(),
                TextInput::make('reference')
                    ->maxLength(64),
                Select::make('status')
                    ->options(collect(ReturnStatus::cases())->mapWithKeys(
                        static fn (ReturnStatus $status): array => [$status->value => $status->value],
                    )->all())
                    ->default(ReturnStatus::Draft->value)
                    ->required()
                    ->disabledOn('edit'),
                DateTimePicker::make('processed_at')
                    ->disabled(),
                Repeater::make('lines')
                    ->relationship()
                    ->schema([
                        Select::make('item_id')
                            ->relationship('item', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('warehouse_id')
                            ->relationship('warehouse', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('purchase_order_line_id')
                            ->label('Purchase order line')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->options(static fn (Get $get): array => self::purchaseOrderLineOptions($get))
                            ->getOptionLabelUsing(static fn (int|string|null $value): ?string => self::purchaseOrderLineLabel($value)),
                        Select::make('invoice_line_id')
                            ->label('Purchase invoice line')
                            ->searchable()
                            ->preload()
                            ->options(static fn (Get $get): array => self::purchaseInvoiceLineOptions($get))
                            ->getOptionLabelUsing(static fn (int|string|null $value): ?string => self::purchaseInvoiceLineLabel($value)),
                        TextInput::make('quantity')
                            ->numeric()
                            ->minValue(0.0001)
                            ->required(),
                        TextInput::make('unit_price')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Defaults to the source purchase invoice line price.'),
                    ])
                    ->defaultItems(1)
                    ->minItems(1)
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function purchaseOrderLineOptions(Get $get): array
    {
        $purchase_order_id = $get('../../purchase_order_id');

        if ($purchase_order_id === null) {
            return [];
        }

        return PurchaseOrderLine::query()
            ->where('purchase_order_id', (int) $purchase_order_id)
            ->orderBy('id')
            ->get()
            ->mapWithKeys(static fn (PurchaseOrderLine $line): array => [
                (int) $line->id => self::formatPurchaseOrderLineLabel($line),
            ])
            ->all();
    }

    private static function purchaseOrderLineLabel(int|string|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        /** @var PurchaseOrderLine|null $line */
        $line = PurchaseOrderLine::query()->find((int) $value);

        return $line === null ? null : self::formatPurchaseOrderLineLabel($line);
    }

    private static function formatPurchaseOrderLineLabel(PurchaseOrderLine $line): string
    {
        return "#{$line->id} {$line->name} - {$line->qty_ordered} @ {$line->unit_price}";
    }

    /**
     * @return array<int, string>
     */
    private static function purchaseInvoiceLineOptions(Get $get): array
    {
        $purchase_order_line_id = $get('purchase_order_line_id');
        $purchase_order_id = $get('../../purchase_order_id');

        if ($purchase_order_line_id === null && $purchase_order_id === null) {
            return [];
        }

        return InvoiceLine::query()
            ->whereHas('invoice', static fn (Builder $query): Builder => $query
                ->where('direction', InvoiceDirection::Purchase->value)
                ->where('invoice_type', InvoiceType::Invoice->value))
            ->when(
                $purchase_order_line_id !== null,
                static fn (Builder $query): Builder => $query->where('purchase_order_line_id', (int) $purchase_order_line_id),
            )
            ->when(
                $purchase_order_line_id === null && $purchase_order_id !== null,
                static function (Builder $query) use ($purchase_order_id): Builder {
                    $line_ids = PurchaseOrderLine::query()
                        ->where('purchase_order_id', (int) $purchase_order_id)
                        ->pluck('id');

                    return $query->whereIn('purchase_order_line_id', $line_ids);
                },
            )
            ->orderBy('invoice_id')
            ->orderBy('line_no')
            ->get()
            ->mapWithKeys(static fn (InvoiceLine $line): array => [
                (int) $line->id => self::formatPurchaseInvoiceLineLabel($line),
            ])
            ->all();
    }

    private static function purchaseInvoiceLineLabel(int|string|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        /** @var InvoiceLine|null $line */
        $line = InvoiceLine::query()->find((int) $value);

        return $line === null ? null : self::formatPurchaseInvoiceLineLabel($line);
    }

    private static function formatPurchaseInvoiceLineLabel(InvoiceLine $line): string
    {
        return "#{$line->invoice_id}/{$line->line_no} {$line->description} - {$line->quantity} @ {$line->unit_price}";
    }
}
