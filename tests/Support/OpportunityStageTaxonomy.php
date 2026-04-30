<?php

declare(strict_types=1);

namespace Modules\ERP\Tests\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\ERP\Database\Seeders\ERPDatabaseSeeder;
use Modules\ERP\Models\Entity;
use Modules\ERP\Models\Pivot\Presettable;

/**
 * Builds a minimal opportunity_stage taxonomy row for ERP feature tests.
 *
 * Does not set is_deleted or is_locked on insert; those are generated from soft-delete and lock timestamps (SQLite).
 */
final class OpportunityStageTaxonomy
{
    /**
     * Runs {@see ERPDatabaseSeeder}, inserts one taxonomy and translation row, returns taxonomy id.
     *
     * @param  non-empty-string  $translationSlug  Used to build a unique taxonomies_translations.slug
     */
    public static function insertMinimalId(string $translationSlug): int
    {
        Artisan::call('db:seed', ['--class' => ERPDatabaseSeeder::class, '--no-interaction' => true]);

        $entity = Entity::query()->withoutGlobalScopes()->where('name', 'opportunity_stage')->firstOrFail();

        $presettable = Presettable::query()->withoutGlobalScopes()
            ->where('entity_id', $entity->id)
            ->whereNull('deleted_at')
            ->latest('id')
            ->firstOrFail();

        $now = now();

        $stage_id = DB::table('taxonomies')->insertGetId([
            'entity_id' => $entity->id,
            'presettable_id' => $presettable->id,
            'shared_components' => null,
            'parent_id' => null,
            'logo' => null,
            'logo_full' => null,
            'is_active' => 1,
            'order_column' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
            'locked_at' => null,
            'locked_user_id' => null,
            'valid_from' => $now,
            'valid_to' => null,
        ]);

        DB::table('taxonomies_translations')->insert([
            'taxonomy_id' => $stage_id,
            'locale' => 'en',
            'name' => 'New',
            'slug' => Str::slug($translationSlug),
            'components' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return $stage_id;
    }
}
