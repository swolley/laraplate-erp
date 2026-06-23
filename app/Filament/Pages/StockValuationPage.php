<?php

declare(strict_types=1);

namespace Modules\ERP\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Modules\ERP\Models\Company;
use Modules\ERP\Services\Reporting\StockValuationService;
use Override;
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
                    ->required(),
            ]);
    }

    public function generate(): void
    {
        $state = $this->form->getState();

        $service = resolve(StockValuationService::class);

        $this->report_data = $service->generate((int) $state['company_id']);
    }
}
