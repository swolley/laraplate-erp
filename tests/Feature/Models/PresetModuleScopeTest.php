<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Enums\CoreTables;
use Modules\ERP\Casts\EntityType;
use Modules\ERP\Models\Preset;

uses(RefreshDatabase::class);

/**
 * Insert an entity of the given type and one preset under it, bypassing model events.
 */
function erpScopePresetOfType(string $type): int
{
    $connection = (new Preset)->getConnection();
    $suffix = uniqid();

    $entity_id = $connection->table(CoreTables::Entities->value)->insertGetId([
        'name' => "{$type}_{$suffix}",
        'slug' => "{$type}-{$suffix}",
        'type' => $type,
    ]);

    return $connection->table(CoreTables::Presets->value)->insertGetId([
        'entity_id' => $entity_id,
        'name' => "preset_{$suffix}",
    ]);
}

it('reads only presets whose own entity is an ERP type', function (): void {
    $erp_preset = erpScopePresetOfType(EntityType::Activities->value);
    $foreign_preset = erpScopePresetOfType('not_an_erp_type');

    $ids = Preset::query()->withoutGlobalScopes()->pluck('id')->all();

    expect($ids)->toContain($erp_preset)
        ->not->toContain($foreign_preset);
});
