<?php

declare(strict_types=1);

namespace Modules\Business\Database\Seeders;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Business\Casts\EntityType;
use Modules\Business\Models\Entity;
use Modules\Business\Models\Preset;
use Modules\Core\Overrides\Seeder;
use Modules\Core\Services\PresetVersioningService;

/**
 * Bootstraps the Business module: ensures the Activity entity and its standard
 * preset exist, with an initial presettable version.
 *
 * Activity tree (taxonomy nodes) seeding belongs to dev fixtures and lives in
 * `DevBusinessTaxonomySeeder`.
 */
final class BusinessDatabaseSeeder extends Seeder
{
    /**
     * @var Collection<string, Entity>
     */
    private Collection $entities;

    public function run(): void
    {
        if (! Schema::hasTable('entities') || ! Schema::hasTable('presets') || ! Schema::hasTable('presettables')) {
            $this->command?->warn('Skipping BusinessDatabaseSeeder: prerequisite Core tables (entities/presets/presettables) are missing.');

            return;
        }

        Model::unguarded(function (): void {
            $this->defaultEntities();
        });
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
