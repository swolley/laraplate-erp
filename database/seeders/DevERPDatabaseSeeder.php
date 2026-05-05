<?php

declare(strict_types=1);

namespace Modules\ERP\Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Core\Models\Translations\TaxonomyTranslation;
use Modules\Core\Overrides\Seeder;
use Modules\ERP\Casts\EntityType;
use Modules\ERP\Models\Activity;
use Modules\ERP\Models\Entity;
use Modules\ERP\Models\OpportunityStage;
use Modules\ERP\Models\Pivot\Presettable;

/**
 * Dev fixture: default CRM pipeline stages ({@see EntityType::OPPORTUNITY_STAGES}).
 */
final class DevERPDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Model::unguarded(function (): void {
            DB::transaction(function (): void {
                $this->seedOpportunityStages();
                $this->seedActivities();
            });
        });
    }

    private function seedOpportunityStages(): void
    {
        $this->logOperation(OpportunityStage::class);

        /**
         * @var array<int, array{slug: string, it: string, en: string}> $stages
         */
        $stages = [
            ['slug' => 'opp-new', 'it' => 'Nuovo', 'en' => 'New'],
            ['slug' => 'opp-qualified', 'it' => 'Qualificata', 'en' => 'Qualified'],
            ['slug' => 'opp-proposal', 'it' => 'Proposta', 'en' => 'Proposal'],
            ['slug' => 'opp-won', 'it' => 'Vinta', 'en' => 'Won'],
            ['slug' => 'opp-lost', 'it' => 'Persa', 'en' => 'Lost'],
        ];

        $entity_id = Entity::query()
            ->withoutGlobalScopes()
            ->where('name', 'opportunity_stage')
            ->where('type', EntityType::OPPORTUNITY_STAGES->value)
            ->select('id')
            ->first()?->id;

        if (! $entity_id) {
            $this->command?->warn('Skipping opportunity stages: Entity "opportunity_stage" not found. Run ERPDatabaseSeeder first.');

            return;
        }

        $presettable_id = Presettable::query()
            ->where('entity_id', $entity_id)
            ->whereNull('deleted_at')
            ->latest('id')
            ->select('id')
            ->first()?->id;

        if (! $presettable_id) {
            $this->command?->warn('Skipping opportunity stages: no active presettable for entity.');

            return;
        }

        $existing_slugs = TaxonomyTranslation::query()
            ->whereIn('slug', array_column($stages, 'slug'))
            ->pluck('slug')
            ->all();

        foreach ($stages as $node) {
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

    private function seedActivities(): void
    {
        if (! Schema::hasTable('taxonomies') || ! Schema::hasTable('taxonomies_translations')) {
            $this->command?->warn('Skipping activities: taxonomies tables are missing.');

            return;
        }

        $this->logOperation(Activity::class);

        /**
         * @var array<int, array{slug: string, it: string, en: string}> $activities
         */
        $activities = [
            ['slug' => 'software-development', 'it' => 'Sviluppo software',     'en' => 'Software development'],
            ['slug' => 'consulting',           'it' => 'Consulenza',            'en' => 'Consulting'],
            ['slug' => 'project-management',   'it' => 'Project management',    'en' => 'Project management'],
            ['slug' => 'support',              'it' => 'Supporto',              'en' => 'Support'],
            ['slug' => 'training',             'it' => 'Formazione',            'en' => 'Training'],
        ];

        $entity_id = Entity::query()
            ->withoutGlobalScopes()
            ->where('name', 'activity')
            ->where('type', EntityType::ACTIVITIES->value)
            ->select('id')
            ->first()?->id;

        if (! $entity_id) {
            $this->command?->warn('Skipping activities: Entity "activity" not found. Run ERPDatabaseSeeder first.');

            return;
        }

        $presettable_id = Presettable::query()
            ->where('entity_id', $entity_id)
            ->whereNull('deleted_at')
            ->latest('id')
            ->select('id')
            ->first()?->id;

        if (! $presettable_id) {
            $this->command?->warn('Skipping activities: no active presettable for Entity "activity".');

            return;
        }

        $existing_slugs = TaxonomyTranslation::query()
            ->whereIn('slug', array_column($activities, 'slug'))
            ->pluck('slug')
            ->all();

        foreach ($activities as $node) {
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
