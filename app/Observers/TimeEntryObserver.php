<?php

declare(strict_types=1);

namespace Modules\ERP\Observers;

use DateTimeInterface;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Models\TimeEntry;

/**
 * TimeEntry domain guard.
 *
 * On `creating`, the overlap check is already performed declaratively by the
 * `TimeEntryOverlap` validation rule plugged into `TimeEntry::getRules()['create']`
 * via `HasValidations::validateWithRules(CrudExecutor::INSERT)`.
 *
 * On `updating`, however, `HasValidations` invokes `validateWithRules('save')`
 * which does not match the `'update'` rules array key declared on the model;
 * to keep the invariant in place during updates we re-run the same overlap
 * check here, manually, throwing a `ValidationException` on conflict.
 */
final class TimeEntryObserver
{
    public function updating(TimeEntry $time_entry): void
    {
        if ($time_entry->shouldSkipValidation()) {
            return;
        }

        $user_id = $time_entry->getAttribute('user_id');
        $started_at = $time_entry->getAttribute('started_at');
        $ended_at = $time_entry->getAttribute('ended_at');

        if (! is_int($user_id)) {
            return;
        }

        if (! $started_at instanceof DateTimeInterface && ! is_string($started_at)) {
            return;
        }

        $normalized_ended_at = $ended_at instanceof DateTimeInterface || is_string($ended_at) || $ended_at === null
            ? $ended_at
            : null;

        if (TimeEntry::existsOverlapFor($user_id, $started_at, $normalized_ended_at, $this->entryId($time_entry))) {
            throw ValidationException::withMessages([
                'ended_at' => ['The ended_at overlaps with another time entry for the same user.'],
            ]);
        }
    }

    private function entryId(TimeEntry $time_entry): int
    {
        $id = $time_entry->getAttribute('id');

        if (! is_int($id)) {
            return 0;
        }

        return $id;
    }
}
