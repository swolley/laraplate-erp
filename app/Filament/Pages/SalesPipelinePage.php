<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Modules\ERP\Models\Company;
use Modules\ERP\Services\Reporting\SalesPipelineService;
use Override;
use UnitEnum;

final class SalesPipelinePage extends Page
{
    public ?array $data = [];

    /**
     * @var array<string, mixed>
     */
    public array $report_data = [];

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    #[Override]
    protected static ?int $navigationSort = 83;

    #[Override]
    protected static ?string $navigationLabel = 'Sales Pipeline';

    #[Override]
    protected static ?string $title = 'Sales Pipeline';

    #[Override]
    protected static ?string $slug = 'sales-pipeline';

    #[Override]
    protected string $view = 'erp::filament.pages.sales-pipeline';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Select::make('company_id')
                    ->label('Company')
                    ->options(Company::query()->pluck('name', 'id')->all())
                    ->required(),
            ]);
    }

    public function generate(): void
    {
        $state = $this->form->getState();

        $service = resolve(SalesPipelineService::class);

        $this->report_data = $service->generate((int) $state['company_id']);
    }
}
