<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Pages;

use BackedEnum;
use DateTimeImmutable;
use Filament\Pages\Page;
use Filament\Schemas\Components\DatePicker;
use Filament\Schemas\Components\Select;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Modules\ERP\Models\Company;
use Modules\ERP\Services\Reporting\TrialBalanceService;
use Override;
use UnitEnum;

final class TrialBalancePage extends Page
{
    public ?array $data = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $report_data = [];

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 80;

    #[Override]
    protected static ?string $navigationLabel = 'Trial Balance';

    #[Override]
    protected static ?string $title = 'Trial Balance';

    #[Override]
    protected string $view = 'erp::filament.pages.trial-balance';

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

        $service = resolve(TrialBalanceService::class);

        $this->report_data = $service->generate(
            (int) $state['company_id'],
            new DateTimeImmutable($state['as_of_date']),
        );
    }
}
