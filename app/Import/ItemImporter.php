<?php

declare(strict_types=1);

namespace Modules\ERP\Import;

use function Modules\ERP\Helpers\current_company_id;

use Illuminate\Support\Facades\Validator;
use Modules\Core\Import\Contracts\EntityImporterInterface;
use Modules\Core\Import\Enums\ImportRowOutcome;
use Modules\Core\Import\Exceptions\RowImportException;
use Modules\Core\Import\Support\ImportRowContext;
use Modules\Core\Import\Support\RecordOriginRegistry;
use Modules\Core\Import\ValueObjects\ExternalRecordIdentity;
use Modules\Core\Import\ValueObjects\ImportField;
use Modules\ERP\Models\Company;
use Modules\ERP\Models\Item;
use Override;

/**
 * Bulk-imports stockable items (materials/products) into ERP. The natural key is
 * `(company_id, sku)`, so re-importing the same file updates rather than
 * duplicates. The company is resolved from an optional `company` column (by slug
 * or name) or, absent that, from the active company context — an import with
 * neither is a row error rather than a silently company-less item.
 *
 * A plain create is safe here: an item carries no posting, numbering or inventory
 * side effect on insert (stock levels and movements are separate).
 */
final readonly class ItemImporter implements EntityImporterInterface
{
    /**
     * @var list<string>
     */
    private const array COSTING_METHODS = ['fifo', 'weighted_avg'];

    public function __construct(private RecordOriginRegistry $origins) {}

    #[Override]
    public function key(): string
    {
        return 'erp.item';
    }

    #[Override]
    public function label(): string
    {
        return 'Items (materials/products)';
    }

    /**
     * @return list<ImportField>
     */
    #[Override]
    public function fields(): array
    {
        return [
            new ImportField('sku', 'SKU', required: true, aliases: ['code', 'item_code']),
            new ImportField('name', 'Name', required: true, aliases: ['description']),
            new ImportField('uom', 'Unit of measure', aliases: ['unit', 'um']),
            new ImportField('costing_method', 'Costing method', aliases: ['costing']),
            new ImportField('company', 'Company', aliases: ['company_slug', 'company_name']),
        ];
    }

    /**
     * @param  array<string, string>  $row
     */
    #[Override]
    public function import(array $row, ImportRowContext $context): ImportRowOutcome
    {
        $sku = mb_trim($row['sku'] ?? '');
        $name = mb_trim($row['name'] ?? '');
        $uom = mb_trim($row['uom'] ?? '') ?: 'unit';
        $costing = mb_strtolower(mb_trim($row['costing_method'] ?? '')) ?: 'fifo';

        $validator = Validator::make(
            ['sku' => $sku, 'name' => $name, 'costing_method' => $costing],
            [
                'sku' => ['required', 'string', 'max:64'],
                'name' => ['required', 'string', 'max:255'],
                'costing_method' => ['required', 'in:' . implode(',', self::COSTING_METHODS)],
            ],
        );

        if ($validator->fails()) {
            throw RowImportException::withErrors($validator->errors()->messages());
        }

        $companyId = $this->resolveCompanyId($row['company'] ?? '');

        $existing = Item::query()->where('company_id', $companyId)->where('sku', $sku)->first();
        $item = $existing ?? new Item(['company_id' => $companyId, 'sku' => $sku]);

        $item->fill(['name' => $name, 'uom' => $uom, 'costing_method' => $costing]);
        $item->save();

        $this->origins->register(
            $item,
            new ExternalRecordIdentity(
                $context->sourceKey(),
                $companyId . ':' . $sku,
                hash('sha256', (string) json_encode($row)),
            ),
            $context->session->original_filename,
        );

        return $existing instanceof Item ? ImportRowOutcome::Updated : ImportRowOutcome::Created;
    }

    /**
     * The company for this row: an explicit `company` (slug or name) if given,
     * otherwise the active company context. A named-but-unknown company, or no
     * company at all, is a row error.
     */
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
