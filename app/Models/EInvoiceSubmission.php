<?php

declare(strict_types=1);

namespace Modules\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Overrides\Model;
use Modules\ERP\Casts\EInvoiceSubmissionStatus;
use Modules\ERP\Concerns\BelongsToCompany;
use Override;
use Overtrue\LaravelVersionable\VersionStrategy;

/**
 * Tracks outbound e-invoice submission attempts per invoice and logical provider.
 *
 * @mixin IdeHelperEInvoiceSubmission
 */
class EInvoiceSubmission extends Model
{
    use BelongsToCompany;

    protected VersionStrategy $versionStrategy = VersionStrategy::DIFF;

    protected $fillable = [
        'company_id',
        'invoice_id',
        'provider_code',
        'external_id',
        'status',
        'last_payload_path',
        'submitted_at',
        'response_payload',
    ];

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    #[Override]
    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules['create'] = array_merge($rules['create'], [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'provider_code' => ['required', 'string', 'max:64'],
            'external_id' => ['nullable', 'string', 'max:191'],
            'status' => ['required', 'string', EInvoiceSubmissionStatus::validationRule()],
            'last_payload_path' => ['nullable', 'string'],
            'submitted_at' => ['nullable', 'date'],
            'response_payload' => ['nullable', 'array'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'invoice_id' => ['sometimes', 'integer', 'exists:invoices,id'],
            'provider_code' => ['sometimes', 'string', 'max:64'],
            'external_id' => ['nullable', 'string', 'max:191'],
            'status' => ['sometimes', 'string', EInvoiceSubmissionStatus::validationRule()],
            'last_payload_path' => ['nullable', 'string'],
            'submitted_at' => ['nullable', 'date'],
            'response_payload' => ['nullable', 'array'],
        ]);

        return $rules;
    }

    protected function casts(): array
    {
        return [
            'status' => EInvoiceSubmissionStatus::class,
            'submitted_at' => 'immutable_datetime',
            'response_payload' => 'array',
        ];
    }
}
