<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Validation\ValidationException;
use Modules\Core\Overrides\Model;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Override;

/**
 * Immutable archive row for a generated ERP report snapshot.
 *
 * @property int|string $id
 * @property int $company_id
 * @property string $report_key
 * @property string $title
 * @property array<string, mixed>|null $parameters
 * @property array<string, mixed> $snapshot_payload
 * @property string|null $csv_content
 * @property string|null $pdf_content
 * @property string $content_hash
 * @property \Carbon\CarbonInterface $generated_at
 * @property bool $is_immutable
 * @mixin \Eloquent
 * @mixin IdeHelperReportSnapshot
 */
final class ReportSnapshot extends Model
{
    use BelongsToCompany;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::ReportSnapshots->value;

    #[Override]
    protected $fillable = [
        'company_id',
        'report_key',
        'title',
        'parameters',
        'snapshot_payload',
        'csv_content',
        'pdf_content',
        'content_hash',
        'generated_at',
        'is_immutable',
    ];

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'company_id' => ['required', 'integer', 'exists:' . ERPTables::Companies->value . ',id'],
            'report_key' => ['required', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:255'],
            'parameters' => ['nullable', 'json'],
            'snapshot_payload' => ['required', 'json'],
            'csv_content' => ['nullable', 'string'],
            'pdf_content' => ['nullable', 'string'],
            'content_hash' => ['required', 'string', 'max:128'],
            'generated_at' => ['required', 'date'],
            'is_immutable' => ['boolean'],
        ]);
        $rules['update'] = $rules['create'];

        return $rules;
    }

    protected static function booted(): void
    {
        self::saving(static function (ReportSnapshot $snapshot): void {
            if ($snapshot->exists && $snapshot->is_immutable) {
                throw ValidationException::withMessages([
                    'snapshot' => ['Report snapshots are immutable.'],
                ]);
            }
        });

        self::deleting(static function (ReportSnapshot $snapshot): void {
            if ($snapshot->is_immutable) {
                throw ValidationException::withMessages([
                    'snapshot' => ['Report snapshots are immutable.'],
                ]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'snapshot_payload' => 'array',
            'generated_at' => 'immutable_datetime',
            'is_immutable' => 'boolean',
        ];
    }

    protected function shouldVersioning(): bool
    {
        return false;
    }
}
