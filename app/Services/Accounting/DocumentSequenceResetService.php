<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Accounting;

use Illuminate\Validation\ValidationException;
use Modules\ERP\Models\DocumentSequence;
use Modules\ERP\Support\ConnectionScopedModels;
use Modules\ERP\Support\ConnectionScopedTransaction;

final class DocumentSequenceResetService
{
    public function reset(DocumentSequence $sequence, int $last_number): void
    {
        if ($last_number < 0) {
            throw ValidationException::withMessages([
                'last_number' => ['Last number cannot be negative.'],
            ]);
        }

        ConnectionScopedTransaction::run($sequence, static function (ConnectionScopedModels $models) use ($sequence, $last_number): void {
            $locked = $models->query(DocumentSequence::class)
                ->withoutGlobalScopes()
                ->lockForUpdate()
                ->whereKey($sequence->id)
                ->firstOrFail();

            $locked->last_number = $last_number;
            $locked->save();
        });
    }
}
