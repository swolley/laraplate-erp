<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Place;
use Modules\Core\Models\Translations\TaxonomyTranslation;
use Modules\ERP\Database\Seeders\ERPDatabaseSeeder;
use Modules\ERP\Models\Activity;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Entity;
use Modules\ERP\Models\Pivot\Presettable;
use Modules\ERP\Models\Site;
use Modules\ERP\Models\Task;
use Modules\ERP\Services\Calendar\TaskIcsExporter;

uses(RefreshDatabase::class);

it('exports a task as a folded RFC 5545 event with canonical place location', function (): void {
    $this->seed(ERPDatabaseSeeder::class);
    $company = Company::query()->withoutGlobalScopes()->where('is_default', true)->firstOrFail();
    $entity = Entity::query()->withoutGlobalScopes()->where('name', 'activity')->firstOrFail();
    $presettable = Presettable::query()->where('entity_id', $entity->id)->firstOrFail();
    $activity = Activity::query()->forceCreate([
        'parent_id' => null, 'presettable_id' => $presettable->id, 'entity_id' => $entity->id,
    ]);
    TaxonomyTranslation::query()->forceCreate([
        'taxonomy_id' => $activity->id, 'locale' => app()->getLocale(),
        'name' => 'On-site installation', 'slug' => 'on-site-installation', 'components' => [],
    ]);
    $place = Place::query()->create([
        'address' => 'Via Roma 10', 'postcode' => '20100', 'city' => 'Milano',
        'province' => 'MI', 'country' => 'IT',
    ]);
    $site = Site::query()->create([
        'company_id' => $company->id, 'name' => 'Customer site', 'place_id' => $place->id,
        'valid_from' => '2026-01-01 00:00:00',
    ]);
    $task = Task::query()->create([
        'site_id' => $site->id, 'taxonomy_id' => $activity->id,
        'valid_from' => '2026-08-01 09:00:00', 'valid_to' => '2026-08-01 11:30:00',
    ]);

    $ics = app(TaskIcsExporter::class)->export($task);

    expect($ics)->toContain("BEGIN:VCALENDAR\r\n")
        ->toContain('UID:erp-task-'.$task->id.'@laraplate')
        ->toContain('DTSTART:'.$task->valid_from->utc()->format('Ymd\THis\Z'))
        ->toContain('DTEND:'.$task->valid_to->utc()->format('Ymd\THis\Z'))
        ->toContain('SUMMARY:'.str_replace(["\\", ';', ','], ["\\\\", '\\;', '\\,'], $activity->name))
        ->toContain('LOCATION:Via Roma 10\\, 20100\\, Milano\\, MI\\, IT')
        ->and(collect(explode("\r\n", $ics))->filter()->every(static fn (string $line): bool => strlen($line) <= 75))->toBeTrue();
});
