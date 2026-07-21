<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\ERP\Enums\ERPTables;

/**
 * Adds database-level safety nets for immutable ERP document chains.
 *
 * SQLite and Oracle use the equivalent Eloquent guards because portable trigger
 * DDL is not available through Laravel's schema builder.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const array LOCKABLE_TABLES = [
        ERPTables::Quotations->value,
        ERPTables::SalesOrders->value,
        ERPTables::Projects->value,
    ];

    public function up(): void
    {
        match (DB::getDriverName()) {
            'mysql', 'mariadb' => $this->installMysqlTriggers(),
            'pgsql' => $this->installPostgresTriggers(),
            default => null,
        };
    }

    public function down(): void
    {
        match (DB::getDriverName()) {
            'mysql', 'mariadb' => $this->dropMysqlTriggers(),
            'pgsql' => $this->dropPostgresTriggers(),
            default => null,
        };
    }

    private function installMysqlTriggers(): void
    {
        foreach (self::LOCKABLE_TABLES as $table) {
            DB::unprepared("CREATE TRIGGER {$table}_lock_guard_update
                BEFORE UPDATE ON {$table} FOR EACH ROW
                BEGIN
                    IF OLD.locked_at IS NOT NULL AND NEW.locked_at IS NOT NULL THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot modify a locked record.';
                    END IF;
                END");
            DB::unprepared("CREATE TRIGGER {$table}_lock_guard_delete
                BEFORE DELETE ON {$table} FOR EACH ROW
                BEGIN
                    IF OLD.locked_at IS NOT NULL THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot delete a locked record.';
                    END IF;
                END");
        }

        DB::unprepared($this->mysqlSalesOrderChainTrigger('insert'));
        DB::unprepared($this->mysqlSalesOrderChainTrigger('update'));
        DB::unprepared($this->mysqlDeliveryLineChainTrigger('insert'));
        DB::unprepared($this->mysqlDeliveryLineChainTrigger('update'));

        $lines = ERPTables::SalesOrderLines->value;
        DB::unprepared("CREATE TRIGGER {$lines}_commercial_lock_guard_update
            BEFORE UPDATE ON {$lines} FOR EACH ROW
            BEGIN
                IF OLD.locked_at IS NOT NULL AND (
                    NOT (NEW.sales_order_id <=> OLD.sales_order_id)
                    OR NOT (NEW.quotation_item_id <=> OLD.quotation_item_id)
                    OR NOT (NEW.item_id <=> OLD.item_id)
                    OR NOT (NEW.name <=> OLD.name)
                    OR NOT (NEW.qty_ordered <=> OLD.qty_ordered)
                    OR NOT (NEW.unit_price <=> OLD.unit_price)
                ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot modify commercial fields on a locked sales order line.';
                END IF;
            END");
        DB::unprepared("CREATE TRIGGER {$lines}_lock_guard_delete
            BEFORE DELETE ON {$lines} FOR EACH ROW
            BEGIN
                IF OLD.locked_at IS NOT NULL THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot delete a locked sales order line.';
                END IF;
            END");
    }

    private function installPostgresTriggers(): void
    {
        DB::unprepared("CREATE OR REPLACE FUNCTION erp_locked_record_guard() RETURNS trigger AS $$
            BEGIN
                IF OLD.locked_at IS NOT NULL AND (TG_OP = 'DELETE' OR NEW.locked_at IS NOT NULL) THEN
                    RAISE EXCEPTION 'Cannot modify or delete a locked record.';
                END IF;
                RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
            END;
        $$ LANGUAGE plpgsql");

        foreach (self::LOCKABLE_TABLES as $table) {
            DB::unprepared("CREATE TRIGGER {$table}_lock_guard_update BEFORE UPDATE ON {$table}
                FOR EACH ROW EXECUTE FUNCTION erp_locked_record_guard()");
            DB::unprepared("CREATE TRIGGER {$table}_lock_guard_delete BEFORE DELETE ON {$table}
                FOR EACH ROW EXECUTE FUNCTION erp_locked_record_guard()");
        }

        $quotations = ERPTables::Quotations->value;
        $projects = ERPTables::Projects->value;
        DB::unprepared("CREATE OR REPLACE FUNCTION erp_lock_sales_order_chain() RETURNS trigger AS $$
            BEGIN
                IF NEW.status IN ('confirmed', 'partially_evased', 'fully_evased') THEN
                    UPDATE {$quotations} SET locked_at = COALESCE(locked_at, CURRENT_TIMESTAMP)
                        WHERE id = NEW.quotation_id AND locked_at IS NULL;
                    UPDATE {$projects} SET locked_at = COALESCE(locked_at, CURRENT_TIMESTAMP)
                        WHERE id = NEW.project_id AND locked_at IS NULL;
                END IF;
                RETURN NEW;
            END;
        $$ LANGUAGE plpgsql");

        $orders = ERPTables::SalesOrders->value;
        DB::unprepared("CREATE TRIGGER {$orders}_lock_chain_insert AFTER INSERT ON {$orders}
            FOR EACH ROW EXECUTE FUNCTION erp_lock_sales_order_chain()");
        DB::unprepared("CREATE TRIGGER {$orders}_lock_chain_update AFTER UPDATE ON {$orders}
            FOR EACH ROW EXECUTE FUNCTION erp_lock_sales_order_chain()");

        $delivery_lines = ERPTables::DeliveryNoteLines->value;
        $order_lines = ERPTables::SalesOrderLines->value;
        DB::unprepared("CREATE OR REPLACE FUNCTION erp_lock_sales_order_line_chain() RETURNS trigger AS $$
            BEGIN
                IF NEW.sales_order_line_id IS NOT NULL THEN
                    UPDATE {$order_lines} SET locked_at = COALESCE(locked_at, CURRENT_TIMESTAMP)
                        WHERE id = NEW.sales_order_line_id AND locked_at IS NULL;
                END IF;
                RETURN NEW;
            END;
        $$ LANGUAGE plpgsql");
        DB::unprepared("CREATE TRIGGER {$delivery_lines}_lock_chain_insert AFTER INSERT ON {$delivery_lines}
            FOR EACH ROW EXECUTE FUNCTION erp_lock_sales_order_line_chain()");
        DB::unprepared("CREATE TRIGGER {$delivery_lines}_lock_chain_update AFTER UPDATE ON {$delivery_lines}
            FOR EACH ROW EXECUTE FUNCTION erp_lock_sales_order_line_chain()");

        DB::unprepared("CREATE OR REPLACE FUNCTION erp_sales_order_line_guard() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' AND OLD.locked_at IS NOT NULL THEN
                    RAISE EXCEPTION 'Cannot delete a locked sales order line.';
                END IF;
                IF TG_OP = 'UPDATE' AND OLD.locked_at IS NOT NULL AND (
                    NEW.sales_order_id IS DISTINCT FROM OLD.sales_order_id
                    OR NEW.quotation_item_id IS DISTINCT FROM OLD.quotation_item_id
                    OR NEW.item_id IS DISTINCT FROM OLD.item_id
                    OR NEW.name IS DISTINCT FROM OLD.name
                    OR NEW.qty_ordered IS DISTINCT FROM OLD.qty_ordered
                    OR NEW.unit_price IS DISTINCT FROM OLD.unit_price
                ) THEN
                    RAISE EXCEPTION 'Cannot modify commercial fields on a locked sales order line.';
                END IF;
                RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
            END;
        $$ LANGUAGE plpgsql");
        DB::unprepared("CREATE TRIGGER {$order_lines}_commercial_lock_guard_update BEFORE UPDATE ON {$order_lines}
            FOR EACH ROW EXECUTE FUNCTION erp_sales_order_line_guard()");
        DB::unprepared("CREATE TRIGGER {$order_lines}_lock_guard_delete BEFORE DELETE ON {$order_lines}
            FOR EACH ROW EXECUTE FUNCTION erp_sales_order_line_guard()");
    }

    private function dropMysqlTriggers(): void
    {
        foreach (self::LOCKABLE_TABLES as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_lock_guard_update");
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_lock_guard_delete");
        }

        foreach ($this->chainTriggerNames() as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }

    private function dropPostgresTriggers(): void
    {
        foreach (self::LOCKABLE_TABLES as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_lock_guard_update ON {$table}");
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_lock_guard_delete ON {$table}");
        }

        $tables = [
            ERPTables::SalesOrders->value,
            ERPTables::SalesOrders->value,
            ERPTables::DeliveryNoteLines->value,
            ERPTables::DeliveryNoteLines->value,
            ERPTables::SalesOrderLines->value,
            ERPTables::SalesOrderLines->value,
        ];
        foreach (array_combine($this->chainTriggerNames(), $tables) as $trigger => $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger} ON {$table}");
        }

        DB::unprepared('DROP FUNCTION IF EXISTS erp_locked_record_guard()');
        DB::unprepared('DROP FUNCTION IF EXISTS erp_lock_sales_order_chain()');
        DB::unprepared('DROP FUNCTION IF EXISTS erp_lock_sales_order_line_chain()');
        DB::unprepared('DROP FUNCTION IF EXISTS erp_sales_order_line_guard()');
    }

    /** @return list<string> */
    private function chainTriggerNames(): array
    {
        return [
            ERPTables::SalesOrders->value . '_lock_chain_insert',
            ERPTables::SalesOrders->value . '_lock_chain_update',
            ERPTables::DeliveryNoteLines->value . '_lock_chain_insert',
            ERPTables::DeliveryNoteLines->value . '_lock_chain_update',
            ERPTables::SalesOrderLines->value . '_commercial_lock_guard_update',
            ERPTables::SalesOrderLines->value . '_lock_guard_delete',
        ];
    }

    private function mysqlSalesOrderChainTrigger(string $operation): string
    {
        $orders = ERPTables::SalesOrders->value;
        $quotations = ERPTables::Quotations->value;
        $projects = ERPTables::Projects->value;

        return "CREATE TRIGGER {$orders}_lock_chain_{$operation} AFTER {$operation} ON {$orders} FOR EACH ROW
            BEGIN
                IF NEW.status IN ('confirmed', 'partially_evased', 'fully_evased') THEN
                    UPDATE {$quotations} SET locked_at = COALESCE(locked_at, CURRENT_TIMESTAMP)
                        WHERE id = NEW.quotation_id AND locked_at IS NULL;
                    UPDATE {$projects} SET locked_at = COALESCE(locked_at, CURRENT_TIMESTAMP)
                        WHERE id = NEW.project_id AND locked_at IS NULL;
                END IF;
            END";
    }

    private function mysqlDeliveryLineChainTrigger(string $operation): string
    {
        $delivery_lines = ERPTables::DeliveryNoteLines->value;
        $order_lines = ERPTables::SalesOrderLines->value;

        return "CREATE TRIGGER {$delivery_lines}_lock_chain_{$operation} AFTER {$operation} ON {$delivery_lines} FOR EACH ROW
            BEGIN
                IF NEW.sales_order_line_id IS NOT NULL THEN
                    UPDATE {$order_lines} SET locked_at = COALESCE(locked_at, CURRENT_TIMESTAMP)
                        WHERE id = NEW.sales_order_line_id AND locked_at IS NULL;
                END IF;
            END";
    }
};
