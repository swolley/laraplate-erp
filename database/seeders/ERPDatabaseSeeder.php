<?php

declare(strict_types=1);

namespace Modules\ERP\Database\Seeders;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\ERP\Casts\EntityType;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Entity;
use Modules\ERP\Models\Preset;
use Modules\ERP\Services\Accounting\ChartOfAccountsInstaller;
use Modules\ERP\Services\Accounting\FiscalCalendarInstaller;
use Modules\Core\Overrides\Seeder;
use Modules\Core\Services\PresetVersioningService;

/**
 * Bootstraps the ERP module: default company (tenant), Activity and opportunity-stage
 * entities, Italian chart of accounts, calendar fiscal year (via FiscalCalendarInstaller),
 * and default Italian tax codes. Taxonomy trees for activities and opportunity stages are
 * seeded by dev fixtures ({@see \Modules\ERP\Database\Seeders\DevERPTaxonomySeeder}).
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

        if (Schema::hasTable('companies')) {
            Model::unguarded(function () use (&$company): void {
                $company = $this->ensureDefaultCompany();
            });
        }

        if (! Schema::hasTable('entities') || ! Schema::hasTable('presets') || ! Schema::hasTable('presettables')) {
            $this->command?->warn('Skipping ERP entity bootstrap: prerequisite Core tables (entities/presets/presettables) are missing.');
        } else {
            Model::unguarded(function (): void {
                $this->defaultEntities();
            });
        }

        if ($company instanceof Company && Schema::hasTable('accounts')) {
            Model::unguarded(function () use ($company): void {
                resolve(ChartOfAccountsInstaller::class)->installWhenEmpty($company);
            });
        }

        if ($company instanceof Company && Schema::hasTable('fiscal_years')) {
            Model::unguarded(function () use ($company): void {
                resolve(FiscalCalendarInstaller::class)->ensureCalendarYear($company, (int) now()->year);
            });
        }

        if ($company instanceof Company && Schema::hasTable('tax_codes')) {
            Model::unguarded(function () use ($company): void {
                resolve(ItalianTaxCodesSeeder::class)->seedForCompany($company);
            });
        }
    }

    private function ensureDefaultCompany(): Company
    {
        $this->logOperation(Company::class);

        $existing = Company::query()->withoutGlobalScopes()->where('is_default', true)->orderBy('id')->first();

        if ($existing instanceof Company) {
            $this->command?->line('    - default company already exists');

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
            'settings' => null,
            'is_default' => true,
        ]);

        $this->command?->line('    - default company <fg=green>created</>');

        return $company;
    }

    private function defaultEntities(): void
    {
        $this->logOperation(Entity::class);

        $this->entities = Entity::query()->withoutGlobalScopes()->get()->keyBy('name');

        DB::transaction(function (): void {
            $entities = [
                [
                    'name' => 'activity',
                    'type' => EntityType::ACTIVITIES,
                    'preset' => 'standard',
                ],
                [
                    'name' => 'opportunity_stage',
                    'type' => EntityType::OPPORTUNITY_STAGES,
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
}
