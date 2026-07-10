<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Accounting;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\ERP\Models\DocumentSequence;

final class DocumentSequenceResetService
{
    public function reset(DocumentSequence $sequence, int $last_number): void
    {
        if ($last_number < 0) {
            throw ValidationException::withMessages([
                'last_number' => ['Last number cannot be negative.'],
            ]);
        }

        DB::transaction(static function () use ($sequence, $last_number): void {
            $locked = DocumentSequence::query()
                ->withoutGlobalScopes()
                ->lockForUpdate()
                ->whereKey($sequence->id)
                ->firstOrFail();

            $locked->last_number = $last_number;
            $locked->save();
        });
    }
}
