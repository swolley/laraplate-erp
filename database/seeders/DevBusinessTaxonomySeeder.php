<?php

declare(strict_types=1);

namespace Modules\Business\Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Business\Casts\EntityType;
use Modules\Business\Models\Activity;
use Modules\Business\Models\Entity;
use Modules\Business\Models\Pivot\Presettable;
use Modules\Core\Models\Translations\TaxonomyTranslation;
use Modules\Core\Overrides\Seeder;

/**
 * Dev-only Activity taxonomy fixture.
 *
 * Inserts a small flat tree of {@see EntityType::ACTIVITIES} nodes plus their
 * Italian/English translations, so the rest of the Business MVP (Tasks,
 * TimeEntries, Quotation lines) can be exercised end-to-end on a fresh DB.
 *
 * Idempotent: re-runs are safe and skip already-seeded nodes.
 *
 * Prerequisites enforced by {@see BusinessDatabaseSeeder}:
 * - `entities`/`presets`/`presettables` rows for the `activity` entity exist.
 * - `taxonomies` and `taxonomies_translations` tables are migrated.
 */
final class DevBusinessTaxonomySeeder extends Seeder
{
    /**
     * @var array<int, array{slug: string, it: string, en: string}>
     */
    private const array DEFAULT_NODES = [
        ['slug' => 'software-development', 'it' => 'Sviluppo software',     'en' => 'Software development'],
        ['slug' => 'consulting',           'it' => 'Consulenza',            'en' => 'Consulting'],
        ['slug' => 'project-management',   'it' => 'Project management',    'en' => 'Project management'],
        ['slug' => 'support',              'it' => 'Supporto',              'en' => 'Support'],
        ['slug' => 'training',             'it' => 'Formazione',            'en' => 'Training'],
    ];

    public function run(): void
    {
        if (! Schema::hasTable('taxonomies') || ! Schema::hasTable('taxonomies_translations')) {
            $this->command?->warn('Skipping DevBusinessTaxonomySeeder: taxonomies tables are missing.');

            return;
        }

        $entity = Entity::query()
            ->withoutGlobalScopes()
            ->where('name', 'activity')
            ->where('type', EntityType::ACTIVITIES->value)
            ->first();

        if (! $entity instanceof Entity) {
            $this->command?->warn('Skipping DevBusinessTaxonomySeeder: Entity "activity" not found. Run BusinessDatabaseSeeder first.');

            return;
        }

        $presettable = Presettable::query()
            ->where('entity_id', $entity->id)
            ->whereNull('deleted_at')
            ->latest('id')
            ->first();

        if (! $presettable instanceof Presettable) {
            $this->command?->warn('Skipping DevBusinessTaxonomySeeder: no active presettable for Entity "activity".');

            return;
        }

        $this->logOperation(Activity::class);

        Model::unguarded(function () use ($entity, $presettable): void {
            DB::transaction(function () use ($entity, $presettable): void {
                $this->seedActivityTree((int) $entity->id, (int) $presettable->id);
            });
        });

        $this->call(DevBusinessOpportunityStagesTaxonomySeeder::class);
    }

    private function seedActivityTree(int $entity_id, int $presettable_id): void
    {
        $existing_slugs = TaxonomyTranslation::query()
            ->whereIn('slug', array_column(self::DEFAULT_NODES, 'slug'))
            ->pluck('slug')
            ->all();

        foreach (self::DEFAULT_NODES as $node) {
            if (in_array($node['slug'], $existing_slugs, true)) {
                $this->command?->line("    - {$node['slug']} already exists");

                continue;
            }

            $activity = Activity::query()->forceCreate([
                'parent_id' => null,
                'presettable_id' => $presettable_id,
                'entity_id' => $entity_id,
            ]);

            foreach (['it', 'en'] as $locale) {
                TaxonomyTranslation::query()->forceCreate([
                    'taxonomy_id' => $activity->id,
                    'locale' => $locale,
                    'name' => $node[$locale],
                    'slug' => Str::slug($node['slug']),
                ]);
            }

            $this->command?->line("    - {$node['slug']} <fg=green>created</>");
        }
    }
}
