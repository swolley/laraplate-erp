<?php

declare(strict_types=1);

namespace Modules\Business\Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Business\Casts\EntityType;
use Modules\Business\Models\Entity;
use Modules\Business\Models\OpportunityStage;
use Modules\Business\Models\Pivot\Presettable;
use Modules\Core\Models\Translations\TaxonomyTranslation;
use Modules\Core\Overrides\Seeder;

/**
 * Dev fixture: default CRM pipeline stages ({@see EntityType::OPPORTUNITY_STAGES}).
 */
final class DevBusinessOpportunityStagesTaxonomySeeder extends Seeder
{
    /**
     * @var array<int, array{slug: string, it: string, en: string}>
     */
    private const array STAGES = [
        ['slug' => 'opp-new', 'it' => 'Nuovo', 'en' => 'New'],
        ['slug' => 'opp-qualified', 'it' => 'Qualificata', 'en' => 'Qualified'],
        ['slug' => 'opp-proposal', 'it' => 'Proposta', 'en' => 'Proposal'],
        ['slug' => 'opp-won', 'it' => 'Vinta', 'en' => 'Won'],
        ['slug' => 'opp-lost', 'it' => 'Persa', 'en' => 'Lost'],
    ];

    public function run(): void
    {
        if (! Schema::hasTable('taxonomies') || ! Schema::hasTable('taxonomies_translations')) {
            $this->command?->warn('Skipping opportunity stages: taxonomies tables are missing.');

            return;
        }

        $entity = Entity::query()
            ->withoutGlobalScopes()
            ->where('name', 'opportunity_stage')
            ->where('type', EntityType::OPPORTUNITY_STAGES->value)
            ->first();

        if (! $entity instanceof Entity) {
            $this->command?->warn('Skipping opportunity stages: Entity "opportunity_stage" not found. Run BusinessDatabaseSeeder first.');

            return;
        }

        $presettable = Presettable::query()
            ->where('entity_id', $entity->id)
            ->whereNull('deleted_at')
            ->latest('id')
            ->first();

        if (! $presettable instanceof Presettable) {
            $this->command?->warn('Skipping opportunity stages: no active presettable for entity.');

            return;
        }

        $this->logOperation(OpportunityStage::class);

        Model::unguarded(function () use ($entity, $presettable): void {
            DB::transaction(function () use ($entity, $presettable): void {
                $this->seedStages((int) $entity->id, (int) $presettable->id);
            });
        });
    }

    private function seedStages(int $entity_id, int $presettable_id): void
    {
        $existing_slugs = TaxonomyTranslation::query()
            ->whereIn('slug', array_column(self::STAGES, 'slug'))
            ->pluck('slug')
            ->all();

        foreach (self::STAGES as $node) {
            if (in_array($node['slug'], $existing_slugs, true)) {
                $this->command?->line("    - opportunity stage {$node['slug']} already exists");

                continue;
            }

            $stage = OpportunityStage::query()->forceCreate([
                'parent_id' => null,
                'presettable_id' => $presettable_id,
                'entity_id' => $entity_id,
            ]);

            foreach (['it', 'en'] as $locale) {
                TaxonomyTranslation::query()->forceCreate([
                    'taxonomy_id' => $stage->id,
                    'locale' => $locale,
                    'name' => $node[$locale],
                    'slug' => Str::slug($node['slug']),
                ]);
            }

            $this->command?->line("    - opportunity stage {$node['slug']} <fg=green>created</>");
        }
    }
}
