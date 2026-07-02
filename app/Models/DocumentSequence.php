<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Modules\Core\Overrides\Model;
use Modules\ERP\Casts\DocumentType;
use Modules\ERP\Concerns\BelongsToCompany;
use Modules\ERP\Enums\ERPTables;
use Override;

/**
 * Per-company document number sequence state (increments under row lock).
 *
 * @property int $padding
 * @property int $last_number
 * @property string $prefix
 * @property string $suffix
 * @property string|null $format_pattern
 * @mixin \Eloquent
 * @mixin IdeHelperDocumentSequence
 */
final class DocumentSequence extends Model
{
    use BelongsToCompany;

    /**
     * @var string
     */
    #[Override]
    protected $table = ERPTables::DocumentSequences->value;

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
    protected $fillable = [
        'company_id',
        'document_type',
        'fiscal_year',
        'last_number',
        'gap_allowed',
        'prefix',
        'padding',
        'format_pattern',
        'suffix',
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
            'document_type' => ['required', 'string', DocumentType::validationRule()],
            'fiscal_year' => ['required', 'integer', 'min:0', 'max:2100'],
            'last_number' => ['required', 'integer', 'min:0'],
            'gap_allowed' => ['sometimes', 'boolean'],
            'prefix' => ['sometimes', 'string', 'max:32'],
            'padding' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'format_pattern' => ['nullable', 'string', 'max:255'],
            'suffix' => ['sometimes', 'string', 'max:32'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'document_type' => ['sometimes', 'string', DocumentType::validationRule()],
            'fiscal_year' => ['sometimes', 'integer', 'min:0', 'max:2100'],
            'last_number' => ['sometimes', 'integer', 'min:0'],
            'gap_allowed' => ['sometimes', 'boolean'],
            'prefix' => ['sometimes', 'string', 'max:32'],
            'padding' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'format_pattern' => ['nullable', 'string', 'max:255'],
            'suffix' => ['sometimes', 'string', 'max:32'],
        ]);

        return $rules;
    }

    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'fiscal_year' => 'integer',
            'last_number' => 'integer',
            'gap_allowed' => 'boolean',
            'padding' => 'integer',
        ];
    }
}
