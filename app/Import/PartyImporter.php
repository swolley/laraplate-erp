<?php

declare(strict_types=1);

namespace Modules\ERP\Import;

use function Modules\ERP\Helpers\current_company_id;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Modules\Core\Import\Contracts\EntityImporterInterface;
use Modules\Core\Import\Enums\ImportRowOutcome;
use Modules\Core\Import\Exceptions\RowImportException;
use Modules\Core\Import\Support\ImportRowContext;
use Modules\Core\Import\Support\RecordOriginRegistry;
use Modules\Core\Import\ValueObjects\ExternalRecordIdentity;
use Modules\Core\Import\ValueObjects\ImportField;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Party;
use Override;

/**
 * Bulk-imports business parties (customers/suppliers) into ERP. The parties table
 * has no DB-enforced natural key, so a row dedupes within its company by VAT number
 * when present, otherwise by name. Customer/supplier is expressed by the
 * `is_customer` / `is_supplier` flags (a party may be both). A plain create is safe
 * — a party carries no posting or numbering side effect on insert.
 */
final readonly class PartyImporter implements EntityImporterInterface
{
    public function __construct(private RecordOriginRegistry $origins) {}

    #[Override]
    public function key(): string
    {
        return 'erp.party';
    }

    #[Override]
    public function label(): string
    {
        return 'Parties (customers/suppliers)';
    }

    /**
     * @return list<ImportField>
     */
    #[Override]
    public function fields(): array
    {
        return [
            new ImportField('name', 'Name', required: true, aliases: ['company_name', 'business_name']),
            new ImportField('vat_number', 'VAT number', aliases: ['vat', 'piva', 'partita_iva']),
            new ImportField('tax_id', 'Tax id', aliases: ['codice_fiscale', 'fiscal_code']),
            new ImportField('is_customer', 'Is customer', aliases: ['customer']),
            new ImportField('is_supplier', 'Is supplier', aliases: ['supplier', 'vendor']),
            new ImportField('company', 'Company', aliases: ['company_slug', 'company_name']),
        ];
    }

    /**
     * @param  array<string, string>  $row
     */
    #[Override]
    public function import(array $row, ImportRowContext $context): ImportRowOutcome
    {
        $name = mb_trim($row['name'] ?? '');
        $vat = mb_trim($row['vat_number'] ?? '');

        $validator = Validator::make(['name' => $name], ['name' => ['required', 'string', 'max:255']]);

        if ($validator->fails()) {
            throw RowImportException::withErrors($validator->errors()->messages());
        }

        $companyId = $this->resolveCompanyId($row['company'] ?? '');

        $existing = Party::query()
            ->where('company_id', $companyId)
            ->when(
                $vat !== '',
                static fn (Builder $query): Builder => $query->where('vat_number', $vat),
                static fn (Builder $query): Builder => $query->where('name', $name),
            )
            ->first();

        $party = $existing ?? new Party(['company_id' => $companyId]);

        $attributes = ['name' => $name];

        foreach (['vat_number' => $vat, 'tax_id' => mb_trim($row['tax_id'] ?? '')] as $field => $value) {
            if ($value !== '') {
                $attributes[$field] = $value;
            }
        }

        if (array_key_exists('is_customer', $row)) {
            $attributes['is_customer'] = $this->boolean($row['is_customer']);
        }

        if (array_key_exists('is_supplier', $row)) {
            $attributes['is_supplier'] = $this->boolean($row['is_supplier']);
        }

        $party->fill($attributes);
        $party->save();

        $this->origins->register(
            $party,
            new ExternalRecordIdentity(
                $context->sourceKey(),
                $companyId . ':' . ($vat !== '' ? $vat : $name),
                hash('sha256', (string) json_encode($row)),
            ),
            $context->session->original_filename,
        );

        return $existing instanceof Party ? ImportRowOutcome::Updated : ImportRowOutcome::Created;
    }

    private function boolean(string $value): bool
    {
        return in_array(mb_strtolower(mb_trim($value)), ['1', 'true', 'yes', 'y', 'si', 'sì', 'x'], true);
    }

    private function resolveCompanyId(string $company): int
    {
        $company = mb_trim($company);

        if ($company !== '') {
            $id = Company::query()->where('slug', $company)->orWhere('name', $company)->value('id');

            if ($id === null) {
                throw RowImportException::withErrors(['company' => ["Unknown company [{$company}]."]]);
            }

            return (int) $id;
        }

        $current = current_company_id();

        if ($current === null) {
            throw RowImportException::withErrors(['company' => ['No company given and no active company context.']]);
        }

        return $current;
    }
}
