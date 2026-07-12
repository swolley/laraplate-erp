<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Pages;

use BackedEnum;
use DateTimeImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Modules\ERP\Models\Company;
use Modules\ERP\Services\Reporting\BalanceSheetService;
use Modules\ERP\Services\Reporting\FinancialReportCsvExporter;
use Override;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

final class BalanceSheetPage extends Page
{
    public ?array $data = [];

    /**
     * @var array<string, mixed>
     */
    public array $report_data = [];

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 81;

    #[Override]
    protected static ?string $navigationLabel = 'Balance Sheet';

    #[Override]
    protected static ?string $title = 'Balance Sheet';

    #[Override]
    protected string $view = 'erp::filament.pages.balance-sheet';

    public function mount(): void
    {
        $this->form->fill([
            'as_of_date' => now()->format('Y-m-d'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Select::make('company_id')
                    ->label('Company')
                    ->options(Company::query()->pluck('name', 'id')->all())
                    ->required(),
                DatePicker::make('as_of_date')
                    ->label('As of Date')
                    ->required(),
            ]);
    }

    public function generate(): void
    {
        $state = $this->form->getState();

        $service = resolve(BalanceSheetService::class);

        $this->report_data = $service->generate(
            (int) $state['company_id'],
            new DateTimeImmutable($state['as_of_date']),
        );
    }

    public function exportCsv(): StreamedResponse
    {
        if ($this->report_data === []) {
            $this->generate();
        }

        $csv = resolve(FinancialReportCsvExporter::class)->balanceSheet($this->report_data);

        return response()->streamDownload(
            static function () use ($csv): void {
                echo $csv;
            },
            'balance-sheet.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
