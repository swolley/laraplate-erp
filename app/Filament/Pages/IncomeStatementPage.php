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
use Modules\ERP\Services\Reporting\FinancialReportCsvExporter;
use Modules\ERP\Services\Reporting\IncomeStatementService;
use Override;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

final class IncomeStatementPage extends Page
{
    public ?array $data = [];

    /**
     * @var array<string, mixed>
     */
    public array $report_data = [];

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 82;

    #[Override]
    protected static ?string $navigationLabel = 'Income Statement';

    #[Override]
    protected static ?string $title = 'Income Statement';

    #[Override]
    protected string $view = 'erp::filament.pages.income-statement';

    public function mount(): void
    {
        $this->form->fill([
            'from_date' => now()->startOfYear()->format('Y-m-d'),
            'to_date' => now()->format('Y-m-d'),
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
                DatePicker::make('from_date')
                    ->label('From Date')
                    ->required(),
                DatePicker::make('to_date')
                    ->label('To Date')
                    ->required(),
            ]);
    }

    public function generate(): void
    {
        $state = $this->form->getState();

        $service = resolve(IncomeStatementService::class);

        $this->report_data = $service->generate(
            (int) $state['company_id'],
            new DateTimeImmutable($state['from_date']),
            new DateTimeImmutable($state['to_date']),
        );
    }

    public function exportCsv(): StreamedResponse
    {
        if ($this->report_data === []) {
            $this->generate();
        }

        $csv = resolve(FinancialReportCsvExporter::class)->incomeStatement($this->report_data);

        return response()->streamDownload(
            static function () use ($csv): void {
                echo $csv;
            },
            'income-statement.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
