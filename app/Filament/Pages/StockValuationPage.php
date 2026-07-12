<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Warehouse;
use Modules\ERP\Services\Reporting\OperationalReportCsvExporter;
use Modules\ERP\Services\Reporting\StockValuationService;
use Override;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

final class StockValuationPage extends Page
{
    public ?array $data = [];

    /**
     * @var array<string, mixed>
     */
    public array $report_data = [];

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 84;

    #[Override]
    protected static ?string $navigationLabel = 'Stock Valuation';

    #[Override]
    protected static ?string $title = 'Stock Valuation';

    #[Override]
    protected static ?string $slug = 'stock-valuation';

    #[Override]
    protected string $view = 'erp::filament.pages.stock-valuation';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Select::make('company_id')
                    ->label('Company')
                    ->options(Company::query()->pluck('name', 'id')->all())
                    ->live()
                    ->required(),
                Select::make('warehouse_id')
                    ->label('Warehouse')
                    ->options(fn (): array => $this->warehouseOptions())
                    ->searchable()
                    ->nullable(),
            ]);
    }

    public function generate(): void
    {
        $state = $this->form->getState();

        $service = resolve(StockValuationService::class);

        $this->report_data = $service->generate((int) $state['company_id'], [
            'warehouse_id' => isset($state['warehouse_id']) && $state['warehouse_id'] !== null
                ? (int) $state['warehouse_id']
                : null,
        ]);
    }

    public function exportCsv(): StreamedResponse
    {
        if ($this->report_data === []) {
            $this->generate();
        }

        $csv = resolve(OperationalReportCsvExporter::class)->stockValuation($this->report_data);

        return response()->streamDownload(
            static function () use ($csv): void {
                echo $csv;
            },
            'stock-valuation.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    /**
     * @return array<int, string>
     */
    private function warehouseOptions(): array
    {
        $company_id = (int) ($this->data['company_id'] ?? 0);

        return Warehouse::query()
            ->when($company_id > 0, static fn ($query) => $query->where('company_id', $company_id))
            ->orderBy('code')
            ->get()
            ->mapWithKeys(static fn (Warehouse $warehouse): array => [
                (int) $warehouse->getKey() => sprintf('%s | %s', $warehouse->code, $warehouse->name),
            ])
            ->all();
    }
}
