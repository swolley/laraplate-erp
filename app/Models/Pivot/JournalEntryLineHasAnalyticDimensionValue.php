<?php

declare(strict_types=1);

namespace Modules\ERP\Models\Pivot;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Pivot;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Models\AnalyticDimensionValue;
use Modules\ERP\Models\JournalEntryLine;
use Override;

/**
 * @mixin \Eloquent
 * @mixin IdeHelperJournalEntryLineHasAnalyticDimensionValue
 */
final class JournalEntryLineHasAnalyticDimensionValue extends Pivot
{
    #[Override]
    protected $table = ERPTables::JournalEntryLineAnalyticDimensionValue->value;

    #[Override]
    public $incrementing = true;

    #[Override]
    public $timestamps = true;

    #[Override]
    protected $fillable = ['journal_entry_line_id', 'analytic_dimension_value_id', 'allocation_percent'];

    public function journal_entry_line(): BelongsTo
    {
        return $this->belongsTo(JournalEntryLine::class);
    }

    public function analytic_dimension_value(): BelongsTo
    {
        return $this->belongsTo(AnalyticDimensionValue::class);
    }

    protected function casts(): array
    {
        return ['allocation_percent' => 'decimal:4',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'datetime',
        ];
    }
}
