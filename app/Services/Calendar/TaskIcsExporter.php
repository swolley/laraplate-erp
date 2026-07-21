<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Calendar;

use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Place;
use Modules\ERP\Models\Task;

final class TaskIcsExporter
{
    public function export(Task $task): string
    {
        $task->loadMissing(['taxonomy', 'project', 'site.place']);
        if ($task->valid_from === null) {
            throw ValidationException::withMessages(['valid_from' => ['Task start date is required for calendar export.']]);
        }

        $summary = $task->taxonomy?->name ?: $task->project?->name ?: 'Task #'.$task->getKey();
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Laraplate//ERP Task Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:erp-task-'.$task->getKey().'@laraplate',
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.Date::parse($task->valid_from)->utc()->format('Ymd\THis\Z'),
        ];
        if ($task->valid_to !== null) {
            $lines[] = 'DTEND:'.Date::parse($task->valid_to)->utc()->format('Ymd\THis\Z');
        }
        $lines[] = 'SUMMARY:'.$this->escape($summary);
        $location = $this->location($task->site?->place);
        if ($location !== '') {
            $lines[] = 'LOCATION:'.$this->escape($location);
        }
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_merge(...array_map($this->fold(...), $lines)))."\r\n";
    }

    public function fileName(Task $task): string
    {
        return 'erp-task-'.$task->getKey().'.ics';
    }

    private function location(?Place $place): string
    {
        if ($place === null) { return ''; }
        return implode(', ', array_filter([$place->address, $place->postcode, $place->city, $place->province, $place->country]));
    }

    private function escape(string $value): string
    {
        return str_replace(["\\", "\r\n", "\n", "\r", ';', ','], ["\\\\", '\\n', '\\n', '\\n', '\\;', '\\,'], $value);
    }

    /** @return list<string> */
    private function fold(string $line): array
    {
        $folded = [];
        $limit = 75;
        while (strlen($line) > $limit) {
            $chunk = mb_strcut($line, 0, $limit, 'UTF-8');
            $folded[] = $chunk;
            $line = ' '.mb_strcut($line, strlen($chunk), null, 'UTF-8');
            $limit = 75;
        }
        $folded[] = $line;
        return $folded;
    }
}
