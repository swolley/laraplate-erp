<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Overrides\Model;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Override;

/**
 * @mixin \Eloquent
 * @mixin IdeHelperAnalyticDimension
 */
final class AnalyticDimension extends Model
{
    use BelongsToCompany;

    #[Override]
    protected $table = ERPTables::AnalyticDimensions->value;

    #[Override]
    protected $fillable = ['company_id', 'code', 'name', 'is_active'];

    public function values(): HasMany
    {
        return $this->hasMany(AnalyticDimensionValue::class);
    }

    protected static function booted(): void
    {
        self::saving(static function (AnalyticDimension $dimension): void {
            $dimension->code = strtoupper(trim((string) $dimension->code));
        });
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
