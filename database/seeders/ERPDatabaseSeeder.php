<?php

declare(strict_types=1);

namespace Modules\ERP\Database\Seeders;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Setting;
use Modules\Core\Overrides\Seeder;
use Modules\Core\Services\PresetVersioningService;
use Modules\Core\Support\PermissionName;
use Modules\ERP\Casts\EntityType;
use Modules\ERP\Models\Account;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\DeliveryNote;
use Modules\ERP\Models\DocumentSequence;
use Modules\ERP\Models\Entity;
use Modules\ERP\Models\FiscalPeriod;
use Modules\ERP\Models\FiscalYear;
use Modules\ERP\Models\Invoice;
use Modules\ERP\Models\JournalEntry;
use Modules\ERP\Models\Pivot\Presettable;
use Modules\ERP\Models\Preset;
use Modules\ERP\Models\Quotation;
use Modules\ERP\Models\ReturnOrder;
use Modules\ERP\Models\SalesOrder;
use Modules\ERP\Models\SupplierReturn;
use Modules\ERP\Models\TaxCode;
use Modules\ERP\Services\Accounting\ChartOfAccountsInstaller;
use Modules\ERP\Services\Accounting\FiscalCalendarInstaller;
use Modules\ERP\Services\Company\ErpCompanySettings;
use Modules\ERP\Support\ConnectionScopedTransaction;
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

        $company_model = new Company;
        $entity_model = new Entity;
        $preset_model = new Preset;
        $presettable_model = new Presettable;
        $account_model = new Account;
        $fiscal_year_model = new FiscalYear;
        $tax_code_model = new TaxCode;
        $setting_model = new Setting;
        $permission_model = new Permission;

        if ($company_model->getConnection()->getSchemaBuilder()->hasTable($company_model->getTable())) {
            Model::unguarded(function () use (&$company): void {
                $company = $this->ensureDefaultCompany();
            });
        }

        if (! $entity_model->getConnection()->getSchemaBuilder()->hasTable($entity_model->getTable())
            || ! $preset_model->getConnection()->getSchemaBuilder()->hasTable($preset_model->getTable())
            || ! $presettable_model->getConnection()->getSchemaBuilder()->hasTable($presettable_model->getTable())) {
            $this->command?->warn('Skipping ERP entity bootstrap: prerequisite Core tables (entities/presets/presettables) are missing.');
        } else {
            Model::unguarded(function (): void {
                $this->defaultEntities();
            });
        }

        if ($company instanceof Company && $account_model->getConnection()->getSchemaBuilder()->hasTable($account_model->getTable())) {
            Model::unguarded(function () use ($company): void {
                resolve(ChartOfAccountsInstaller::class)->installWhenEmpty($company);
            });
        }

        if ($company instanceof Company && $fiscal_year_model->getConnection()->getSchemaBuilder()->hasTable($fiscal_year_model->getTable())) {
            Model::unguarded(function () use ($company): void {
                resolve(FiscalCalendarInstaller::class)->ensureCalendarYear($company, (int) now()->year);
            });
        }

        if ($company instanceof Company && $tax_code_model->getConnection()->getSchemaBuilder()->hasTable($tax_code_model->getTable())) {
            Model::unguarded(function () use ($company): void {
                resolve(ItalianTaxCodesSeeder::class)->seedForCompany($company);
            });
        }

        if ($setting_model->getConnection()->getSchemaBuilder()->hasTable($setting_model->getTable())) {
            Model::unguarded(function (): void {
                $this->ensureGlobalErpSettings();
            });
        }

        if ($permission_model->getConnection()->getSchemaBuilder()->hasTable($permission_model->getTable())) {
            Model::unguarded(function (): void {
                $this->ensureDomainPermissions();
            });
        }
    }

    private function ensureDefaultCompany(): Company
    {
        $this->logOperation(Company::class);

        $existing = (new Company)->newQuery()->withoutGlobalScopes()->where('is_default', true)->orderBy('id')->first();

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
        $setting_model = new Setting;

        foreach (ErpCompanySettings::globalSettingDefinitions() as $definition) {
            if ($setting_model->newQuery()->withoutGlobalScopes()->where('name', $definition['name'])->exists()) {
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

        $entity_model = new Entity;
        $preset_model = new Preset;
        ConnectionScopedTransaction::connection($entity_model, $preset_model);
        $this->entities = $entity_model->newQuery()->withoutGlobalScopes()->get()->keyBy('name');

        $entity_model->getConnection()->transaction(function (): void {
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
        $permission_model = new Permission;

        foreach ($this->domainPermissions() as $permission_name) {
            $permission_model->newQuery()->firstOrCreate(['name' => $permission_name]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->line('    - ERP domain permissions <fg=green>updated</>');
    }

    /**
     * @return list<string>
     */
    private function domainPermissions(): array
    {
        $entities = [DeliveryNote::class, DocumentSequence::class, FiscalPeriod::class, Invoice::class, JournalEntry::class, Quotation::class, SalesOrder::class];
        $permissions = [];

        foreach ($entities as $model) {
            $permissions[] = PermissionName::forClass($model, 'post');
            $permissions[] = PermissionName::forClass($model, 'unpost');

            if ($model === Invoice::class) {
                $permissions[] = PermissionName::forClass($model, 'submitEInvoice');
                $permissions[] = PermissionName::forClass($model, 'refreshEInvoice');
                $permissions[] = PermissionName::forClass($model, 'force_post');
            }

            if ($model === FiscalPeriod::class) {
                $permissions[] = PermissionName::forClass($model, 'close');
                $permissions[] = PermissionName::forClass($model, 'reopen');
            }

            if ($model === JournalEntry::class) {
                $permissions[] = PermissionName::forClass($model, 'reverse');
            }

            if ($model === SalesOrder::class) {
                $permissions[] = PermissionName::forClass($model, 'amend');
            }

            if ($model === Quotation::class) {
                $permissions[] = PermissionName::forClass($model, 'unlock');
            }

            if ($model === DocumentSequence::class) {
                $permissions[] = PermissionName::forClass($model, 'reset');
                $permissions[] = PermissionName::forClass($model, 'reserve');
            }
        }

        foreach ([ReturnOrder::class, SupplierReturn::class] as $return_model) {
            foreach (['approve', 'complete', 'cancel', 'reverse_processed'] as $operation) {
                $permissions[] = PermissionName::forClass($return_model, $operation);
            }
        }

        $permissions[] = PermissionName::forClass(ReturnOrder::class, 'create_credit_note');
        $permissions[] = PermissionName::forClass(SupplierReturn::class, 'create_debit_note');

        $permissions[] = PermissionName::forClass(Quotation::class, 'create_revision');

        $permissions[] = PermissionName::forClass(FiscalYear::class, 'close');
        $permissions[] = PermissionName::forClass(Company::class, 'switch_context');
        $permissions[] = PermissionName::forClass(TaxCode::class, 'supersede');

        return $permissions;
    }
}
