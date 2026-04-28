<?php

declare(strict_types=1);

namespace Modules\Business\Observers;

use Illuminate\Validation\ValidationException;
use Modules\Business\Models\TimeEntry;

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

        $user_id = $time_entry->user_id;
        $started_at = $time_entry->started_at;
        $ended_at = $time_entry->ended_at;

        if ($user_id === null || $started_at === null) {
            return;
        }

        if (TimeEntry::existsOverlapFor((int) $user_id, $started_at, $ended_at, $time_entry->getKey())) {
            throw ValidationException::withMessages([
                'ended_at' => ['The ended_at overlaps with another time entry for the same user.'],
            ]);
        }
    }
}
