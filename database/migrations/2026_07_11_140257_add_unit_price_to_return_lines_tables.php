<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Modules\ERP\Enums\ERPTables;
use Modules\ERP\Helpers\ERPMigrateUtils;

return new class extends Migration
{
    private ?Connection $activeConnection = null;

    public function up(): void
    {
        $connection = $this->connection();
        $schema = $connection->getSchemaBuilder();
        $return_order_lines_table = ERPTables::ReturnOrderLines->value;
        $supplier_return_lines_table = ERPTables::SupplierReturnLines->value;

        $schema->table($return_order_lines_table, static function (Blueprint $table): void {
            $table->decimal('unit_price', 15, 4)->nullable();
        });
        $schema->table($supplier_return_lines_table, static function (Blueprint $table) use ($supplier_return_lines_table): void {
            $table->foreignId('invoice_line_id')
                ->nullable()
                ->constrained(ERPTables::InvoiceLines->value, 'id', "{$supplier_return_lines_table}_invoice_line_id_FK")
                ->nullOnDelete();
            $table->decimal('unit_price', 15, 4)->nullable();
        });

        ERPMigrateUtils::nullableNonNegativeCheck($return_order_lines_table, 'rol_up_nn_ck', 'unit_price', $connection);
        ERPMigrateUtils::nullableNonNegativeCheck($supplier_return_lines_table, 'srl_up_nn_ck', 'unit_price', $connection);
    }

    public function down(): void
    {
        $return_order_lines_table = ERPTables::ReturnOrderLines->value;
        $supplier_return_lines_table = ERPTables::SupplierReturnLines->value;

        $this->dropCheckConstraint($return_order_lines_table, 'rol_up_nn_ck');
        $this->dropCheckConstraint($supplier_return_lines_table, 'srl_up_nn_ck');

        $schema = $this->connection()->getSchemaBuilder();
        $schema->table($return_order_lines_table, static function (Blueprint $table): void {
            $table->dropColumn('unit_price');
        });
        $schema->table($supplier_return_lines_table, static function (Blueprint $table) use ($supplier_return_lines_table): void {
            $table->dropForeign("{$supplier_return_lines_table}_invoice_line_id_FK");
            $table->dropColumn('invoice_line_id');
            $table->dropColumn('unit_price');
        });
    }

    private function dropCheckConstraint(string $table, string $constraint): void
    {
        $connection = $this->connection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        if (! in_array($driver, ['mysql', 'mariadb', 'pgsql', 'oracle'], true)) {
            return;
        }

        $wrapped_table = $connection->getSchemaGrammar()->wrapTable($table);
        $wrapped_constraint = $connection->getQueryGrammar()->wrap($constraint);
        $drop = in_array($driver, ['mysql', 'mariadb'], true) ? 'DROP CHECK' : 'DROP CONSTRAINT';

        $connection->statement("ALTER TABLE {$wrapped_table} {$drop} {$wrapped_constraint}");
    }

    private function connection(): Connection
    {
        return $this->activeConnection ??= app('db')->connection();
    }
};
