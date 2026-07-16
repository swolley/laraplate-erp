<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Core\Overrides\Model;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Models\Pivot\JournalEntryLineHasAnalyticDimensionValue;
use Override;

final class AnalyticDimensionValue extends Model
{
    use BelongsToCompany;

    #[Override]
    protected $table = ERPTables::AnalyticDimensionValues->value;

    #[Override]
    protected $fillable = ['company_id', 'analytic_dimension_id', 'code', 'name', 'is_active'];

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(AnalyticDimension::class, 'analytic_dimension_id');
    }

    public function journal_entry_lines(): BelongsToMany
    {
        return $this->belongsToMany(JournalEntryLine::class, ERPTables::JournalEntryLineAnalyticDimensionValue->value)
            ->using(JournalEntryLineHasAnalyticDimensionValue::class)
            ->withPivot(['allocation_percent'])
            ->withTimestamps();
    }

    protected static function booted(): void
    {
        self::saving(static function (AnalyticDimensionValue $value): void {
            $value->code = strtoupper(trim((string) $value->code));
        });
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
