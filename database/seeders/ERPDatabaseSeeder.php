<?php

declare(strict_types=1);

namespace Modules\ERP\Database\Seeders;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Setting;
use Modules\Core\Overrides\Seeder;
use Modules\Core\Services\PresetVersioningService;
use Modules\ERP\Casts\EntityType;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Models\Entity;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Models\Preset;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Services\Accounting\ChartOfAccountsInstaller;
use Modules\ERP\Services\Accounting\FiscalCalendarInstaller;
use Modules\ERP\Services\Company\ErpCompanySettings;
use ReflectionClass;
use Spatie\Permission\PermissionRegistrar;

/**
 * Bootstraps the ERP module: default company (tenant), Activity and opportunity-stage
 * entities, Italian chart of accounts, calendar fiscal year (via FiscalCalendarInstaller),
 * and default Italian tax codes. Taxonomy trees for activities and opportunity stages are
 * seeded by dev fixtures ({@see DevERPTaxonomySeeder}).
 */
final class ERPDatabaseSeeder extends Seeder
{
    /**
     * @var Collection<string, Entity>
     */
    private Collection $entities;

    public function run(): void
    {
        /** @var Company|null $company */
        $company = null;

        $companies_table = ERPTables::Companies->value;
        $entities_table = CoreTables::Entities->value;
        $presets_table = CoreTables::Presets->value;
        $presettables_table = CoreTables::Presettables->value;
        $accounts_table = ERPTables::Accounts->value;
        $fiscal_years_table = ERPTables::FiscalYears->value;
        $tax_codes_table = ERPTables::TaxCodes->value;

        if (Schema::hasTable($companies_table)) {
            Model::unguarded(function () use (&$company): void {
                $company = $this->ensureDefaultCompany();
            });
        }

        if (! Schema::hasTable($entities_table) || ! Schema::hasTable($presets_table) || ! Schema::hasTable($presettables_table)) {
            $this->command?->warn('Skipping ERP entity bootstrap: prerequisite Core tables (entities/presets/presettables) are missing.');
        } else {
            Model::unguarded(function (): void {
                $this->defaultEntities();
            });
        }

        if ($company instanceof Company && Schema::hasTable($accounts_table)) {
            Model::unguarded(function () use ($company): void {
                resolve(ChartOfAccountsInstaller::class)->installWhenEmpty($company);
            });
        }

        if ($company instanceof Company && Schema::hasTable($fiscal_years_table)) {
            Model::unguarded(function () use ($company): void {
                resolve(FiscalCalendarInstaller::class)->ensureCalendarYear($company, (int) now()->year);
            });
        }

        if ($company instanceof Company && Schema::hasTable($tax_codes_table)) {
            Model::unguarded(function () use ($company): void {
                resolve(ItalianTaxCodesSeeder::class)->seedForCompany($company);
            });
        }

        if (Schema::hasTable(CoreTables::Settings->value)) {
            Model::unguarded(function (): void {
                $this->ensureGlobalErpSettings();
            });
        }

        if (Schema::hasTable(CoreTables::Permissions->value)) {
            Model::unguarded(function (): void {
                $this->ensureDomainPermissions();
            });
        }
    }

    private function ensureDefaultCompany(): Company
    {
        $this->logOperation(Company::class);

        $existing = Company::query()->withoutGlobalScopes()->where('is_default', true)->orderBy('id')->first();

        if ($existing instanceof Company) {
            $this->command?->line('    - default company already exists');
            $this->ensureCompanySettings($existing);

            return $existing;
        }

        /** @var Company $company */
        $company = $this->create(Company::class, [
            'slug' => 'default',
            'name' => 'Default company',
            'legal_name' => null,
            'tax_id' => null,
            'fiscal_country' => 'IT',
            'default_currency' => 'EUR',
            'is_default' => true,
        ]);
        $company->settings = ErpCompanySettings::defaultSettings();
        $company->save();

        $this->command?->line('    - default company <fg=green>created</>');

        return $company;
    }

    private function ensureGlobalErpSettings(): void
    {
        foreach (ErpCompanySettings::globalSettingDefinitions() as $definition) {
            if (Setting::query()->withoutGlobalScopes()->where('name', $definition['name'])->exists()) {
                continue;
            }

            Setting::factory()->persistedWithoutApprovalCapture()->create($definition);
            $this->command?->line("    - global ERP setting <fg=green>{$definition['name']}</> created");
        }
    }

    private function ensureCompanySettings(Company $company): void
    {
        $erp_company_settings = resolve(ErpCompanySettings::class);
        $merged = $erp_company_settings->mergeWithDefaults($company);

        if ($company->settings === $merged) {
            return;
        }

        $company->settings = $merged;
        $company->save();

        $this->command?->line('    - default company ERP settings <fg=green>initialized</>');
    }

    private function defaultEntities(): void
    {
        $this->logOperation(Entity::class);

        $this->entities = Entity::query()->withoutGlobalScopes()->get()->keyBy('name');

        DB::transaction(function (): void {
            $entities = [
                [
                    'name' => 'activity',
                    'type' => EntityType::Activities,
                    'preset' => 'standard',
                ],
                [
                    'name' => 'opportunity_stage',
                    'type' => EntityType::OpportunityStages,
                    'preset' => 'standard',
                ],
            ];

            foreach ($entities as $entity) {
                if ($this->entities->has($entity['name'])) {
                    $this->command?->line("    - {$entity['name']} already exists");

                    continue;
                }

                /** @var Entity $new_entity */
                $new_entity = $this->create(Entity::class, [
                    'name' => $entity['name'],
                    'type' => $entity['type'],
                ]);
                $this->entities->put($entity['name'], $new_entity);

                /** @var Preset $preset */
                $preset = $this->create(Preset::class, [
                    'name' => $entity['preset'],
                    'entity_id' => $new_entity->id,
                ]);

                resolve(PresetVersioningService::class)->createVersion($preset);

                $this->command?->line("    - {$entity['name']} <fg=green>created</>");
            }
        });
    }

    private function ensureDomainPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->domainPermissions() as $permission_name) {
            Permission::query()->firstOrCreate(['name' => $permission_name]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->line('    - ERP domain permissions <fg=green>updated</>');
    }

    /**
     * @return list<string>
     */
    private function domainPermissions(): array
    {
        $entities = [DeliveryNote::class, FiscalPeriod::class, Invoice::class, JournalEntry::class, SalesOrder::class];
        $permissions = [];

        foreach ($entities as $model) {
            $instance = new ReflectionClass($model)->newInstanceWithoutConstructor();

            $connection = $instance->getConnectionName() ?? 'default';
            $table = $instance->getTable();

            $permissions[] = "{$connection}." . $table . '.post';
            $permissions[] = "{$connection}." . $table . '.unpost';

            if ($model === Invoice::class) {
                $permissions[] = "{$connection}." . $table . '.submitEInvoice';
                $permissions[] = "{$connection}." . $table . '.refreshEInvoice';
            }
        }

        return $permissions;
    }
}
