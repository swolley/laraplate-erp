<?php

declare(strict_types=1);

namespace Modules\ERP\Rules;

use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\ERP\Models\TimeEntry;
use Override;

/**
 * Application-side validation that prevents overlapping TimeEntry rows
 * for the same user.
 *
 * Overlap definition (half-open interval [started_at, ended_at)):
 *   existing.started_at < new.ended_at
 *   AND (existing.ended_at IS NULL OR existing.ended_at > new.started_at)
 *
 * `ended_at = null` on either side means "session still open" / future infinity.
 *
 * The rule is data-aware: it reads `user_id` and `started_at` directly from the
 * validated payload, so it must be attached to the `ended_at` attribute and the
 * payload must always carry both `user_id` and `started_at`.
 *
 * For update operations, the model primary key must be passed via `$excludeId`
 * to avoid the row being checked against itself.
 */
final class TimeEntryOverlap implements DataAwareRule, ValidationRule
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    public function __construct(private readonly ?int $excludeId = null) {}

    /**
     * @param  array<string, mixed>  $data
     */
    #[Override]
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    #[Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $user_id = $this->data['user_id'] ?? null;
        $started_at = $this->data['started_at'] ?? null;

        if (! is_numeric($user_id)) {
            return;
        }

        if (! is_string($started_at) && ! ($started_at instanceof DateTimeInterface)) {
            return;
        }

        $ended_at = (is_string($value) || $value instanceof DateTimeInterface) ? $value : null;

        if (TimeEntry::existsOverlapFor((int) $user_id, $started_at, $ended_at, $this->excludeId)) {
            $fail('The :attribute overlaps with another time entry for the same user.');
        }
    }
}
