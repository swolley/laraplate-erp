<?php

declare(strict_types=1);

namespace Modules\Business\Models;

use Modules\Business\Casts\DocumentType;
use Modules\Business\Concerns\BelongsToCompany;
use Modules\Core\Overrides\Model;
use Override;

/**
 * Per-company document number sequence state (increments under row lock).
 *
 * @mixin IdeHelperDocumentSequence
 */
class DocumentSequence extends Model
{
    use BelongsToCompany;

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

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
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
