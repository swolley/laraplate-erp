<?php

declare(strict_types=1);

namespace Modules\ERP\Tests\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Enums\CoreTables;
use Modules\ERP\Database\Seeders\ERPDatabaseSeeder;
use Modules\ERP\Models\Entity;
use Modules\ERP\Models\Pivot\Presettable;

/**
 * Builds a minimal activity taxonomy row for ERP feature tests.
 */
final class ActivityTaxonomy
{
    /**
     * @param  non-empty-string  $translationSlug
     */
    public static function insertMinimalId(string $translationSlug): int
    {
        Artisan::call('db:seed', ['--class' => ERPDatabaseSeeder::class, '--no-interaction' => true]);

        $entity = Entity::query()->withoutGlobalScopes()->where('name', 'activity')->firstOrFail();

        $presettable = Presettable::query()->withoutGlobalScopes()
            ->where('entity_id', $entity->id)
            ->whereNull('deleted_at')
            ->latest('id')
            ->firstOrFail();

        $now = now();

        $activity_id = DB::table(CoreTables::Taxonomies->value)->insertGetId([
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

        DB::table(CoreTables::TaxonomiesTranslations->value)->insert([
            'taxonomy_id' => $activity_id,
            'locale' => 'en',
            'name' => 'Development',
            'slug' => Str::slug($translationSlug),
            'components' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return $activity_id;
    }
}
