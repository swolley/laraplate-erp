<?php

declare(strict_types=1);

namespace Modules\Business\Helpers;

use Illuminate\Database\Schema\Blueprint;

/**
 * Schema helpers specific to the Business / ERP domain.
 *
 * Sibling to {@see \Modules\Core\Helpers\MigrateUtils}; lives in the Business
 * module so the Core stays unaware of accounting/multi-currency primitives.
 */
final class BusinessMigrateUtils
{
    /**
     * Add the standard dual-currency money columns to a transactional table.
     *
     * Schema:
     *  - {prefix}amount_doc  decimal(15,4) — amount in the document currency
     *  - {prefix}currency_doc char(3) ISO 4217
     *  - {prefix}amount_local decimal(15,4) — amount in the company functional currency
     *    (used as the basis for double-entry balancing)
     *  - {prefix}currency_local char(3) ISO 4217 (Company.default_currency)
     *  - {prefix}fx_rate decimal(18,8) — multiplier from doc to local
     *
     * Rule of thumb: `amount_local = amount_doc * fx_rate`.
     *
     * Multi-prefix support lets a single row carry several monetary buckets
     * (e.g. `unit_`, `tax_`, `total_`) without naming clashes.
     */
    public static function moneyColumns(
        Blueprint $table,
        string $prefix = '',
        bool $nullableLocal = false,
        bool $nullableFxRate = false,
    ): void {
        $table->decimal("{$prefix}amount_doc", 15, 4)->comment('Amount in the document currency');
        $table->char("{$prefix}currency_doc", 3)->comment('ISO 4217 currency code of the document');

        $local = $table->decimal("{$prefix}amount_local", 15, 4);

        if ($nullableLocal) {
            $local->nullable()->comment('Amount in the company functional currency; null until conversion settles');
        } else {
            $local->comment('Amount in the company functional currency (basis for journal balancing)');
        }

        $table->char("{$prefix}currency_local", 3)->comment('ISO 4217 functional currency (Company.default_currency)');

        $fx = $table->decimal("{$prefix}fx_rate", 18, 8);

        if ($nullableFxRate) {
            $fx->nullable()->comment('Multiplier from doc to local; null until exchange rate is known');
        } else {
            $fx->default(1)->comment('Multiplier from doc to local (1 when both currencies match)');
        }
    }

    /**
     * Add a `company_id` foreign key referencing `companies(id)` to the table,
     * using `restrictOnDelete` so a company cannot be removed while still
     * holding transactional records.
     *
     * Set `$nullable = true` when retrofitting an existing table whose rows
     * pre-date the multi-tenant migration; set `$indexed = true` to add an
     * explicit index when the column is not part of a composite key.
     */
    public static function companyForeign(
        Blueprint $table,
        bool $nullable = false,
        bool $indexed = true,
    ): void {
        $column = $table->foreignId('company_id');

        if ($nullable) {
            $column->nullable();
        }

        $table_name = $table->getTable();
        $foreign_name = "{$table_name}_company_id_FK";

        $column->constrained('companies', 'id', $foreign_name)->restrictOnDelete();

        if ($indexed) {
            $table->index('company_id', "{$table_name}_company_id_idx");
        }
    }
}
