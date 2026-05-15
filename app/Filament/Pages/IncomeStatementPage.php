<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\DatePicker;
use Filament\Schemas\Components\Select;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Modules\ERP\Models\Company;
use Modules\ERP\Services\Reporting\IncomeStatementService;
use Override;
use UnitEnum;

final class IncomeStatementPage extends Page
{
    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[\Override]
    protected static ?int $navigationSort = 82;

    #[\Override]
    protected static ?string $navigationLabel = 'Income Statement';

    #[\Override]
    protected static ?string $title = 'Income Statement';

    #[\Override]
    protected static string $view = 'erp::filament.pages.income-statement';

    public ?array $data = [];

    /** @var array<string, mixed> */
    public array $report_data = [];

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
            new \DateTimeImmutable($state['from_date']),
            new \DateTimeImmutable($state['to_date']),
        );
    }
}
