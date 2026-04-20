<?php

declare(strict_types=1);

namespace Modules\Business\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Reserved for dev-only taxonomy rows (e.g. Activity tree under Business Entity).
 * Requires `taxonomies` and upstream Entity/Preset wiring; safe no-op when tables are absent.
 */
final class DevBusinessTaxonomySeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('taxonomies')) {
            return;
        }

        // Intentionally empty: seeding Activity nodes depends on Entity + Presettables in your environment.
        // Add Activity::withoutGlobalScopes()->create([...]) here once a stable dev fixture exists.
    }
}
