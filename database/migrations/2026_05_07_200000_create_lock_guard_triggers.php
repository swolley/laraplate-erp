<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Modules\ERP\Enums\ERPTables;

/**
 * Adds database-level safety nets for immutable ERP document chains.
 *
 * SQLite and Oracle use the equivalent Eloquent guards because portable trigger
 * DDL is not available through Laravel's schema builder.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const array LOCKABLE_TABLES = [
        ERPTables::Quotations->value,
        ERPTables::SalesOrders->value,
        ERPTables::Projects->value,
    ];

    private ?Connection $activeConnection = null;

    public function up(): void
    {
        match ($this->connection()->getDriverName()) {
            'mysql', 'mariadb' => $this->installMysqlTriggers(),
            'pgsql' => $this->installPostgresTriggers(),
            default => null,
        };
    }

    public function down(): void
    {
        match ($this->connection()->getDriverName()) {
            'mysql', 'mariadb' => $this->dropMysqlTriggers(),
            'pgsql' => $this->dropPostgresTriggers(),
            default => null,
        };
    }

    private function installMysqlTriggers(): void
    {
        foreach (self::LOCKABLE_TABLES as $table) {
            $wrapped_table = $this->table($table);
            $update_trigger = $this->identifier($table . '_lock_guard_update');
            $delete_trigger = $this->identifier($table . '_lock_guard_delete');
            $this->connection()->unprepared("CREATE TRIGGER {$update_trigger}
                BEFORE UPDATE ON {$wrapped_table} FOR EACH ROW
                BEGIN
                    IF OLD.locked_at IS NOT NULL AND NEW.locked_at IS NOT NULL THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot modify a locked record.';
                    END IF;
                END");
            $this->connection()->unprepared("CREATE TRIGGER {$delete_trigger}
                BEFORE DELETE ON {$wrapped_table} FOR EACH ROW
                BEGIN
                    IF OLD.locked_at IS NOT NULL THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot delete a locked record.';
                    END IF;
                END");
        }

        $this->connection()->unprepared($this->mysqlSalesOrderChainTrigger('insert'));
        $this->connection()->unprepared($this->mysqlSalesOrderChainTrigger('update'));
        $this->connection()->unprepared($this->mysqlDeliveryLineChainTrigger('insert'));
        $this->connection()->unprepared($this->mysqlDeliveryLineChainTrigger('update'));

        $lines = $this->table(ERPTables::SalesOrderLines->value);
        $commercial_trigger = $this->identifier(ERPTables::SalesOrderLines->value . '_commercial_lock_guard_update');
        $delete_trigger = $this->identifier(ERPTables::SalesOrderLines->value . '_lock_guard_delete');
        $this->connection()->unprepared("CREATE TRIGGER {$commercial_trigger}
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
        $this->connection()->unprepared("CREATE TRIGGER {$delete_trigger}
            BEFORE DELETE ON {$lines} FOR EACH ROW
            BEGIN
                IF OLD.locked_at IS NOT NULL THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot delete a locked sales order line.';
                END IF;
            END");
    }

    private function installPostgresTriggers(): void
    {
        $record_guard = $this->identifier('erp_locked_record_guard');
        $this->connection()->unprepared("CREATE OR REPLACE FUNCTION {$record_guard}() RETURNS trigger AS $$
            BEGIN
                IF OLD.locked_at IS NOT NULL AND (TG_OP = 'DELETE' OR NEW.locked_at IS NOT NULL) THEN
                    RAISE EXCEPTION 'Cannot modify or delete a locked record.';
                END IF;
                RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
            END;
        $$ LANGUAGE plpgsql");

        foreach (self::LOCKABLE_TABLES as $table) {
            $wrapped_table = $this->table($table);
            $update_trigger = $this->identifier($table . '_lock_guard_update');
            $delete_trigger = $this->identifier($table . '_lock_guard_delete');
            $this->connection()->unprepared("CREATE TRIGGER {$update_trigger} BEFORE UPDATE ON {$wrapped_table}
                FOR EACH ROW EXECUTE FUNCTION {$record_guard}()");
            $this->connection()->unprepared("CREATE TRIGGER {$delete_trigger} BEFORE DELETE ON {$wrapped_table}
                FOR EACH ROW EXECUTE FUNCTION {$record_guard}()");
        }

        $quotations = $this->table(ERPTables::Quotations->value);
        $projects = $this->table(ERPTables::Projects->value);
        $order_chain = $this->identifier('erp_lock_sales_order_chain');
        $this->connection()->unprepared("CREATE OR REPLACE FUNCTION {$order_chain}() RETURNS trigger AS $$
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

        $orders = $this->table(ERPTables::SalesOrders->value);
        $order_insert_trigger = $this->identifier(ERPTables::SalesOrders->value . '_lock_chain_insert');
        $order_update_trigger = $this->identifier(ERPTables::SalesOrders->value . '_lock_chain_update');
        $this->connection()->unprepared("CREATE TRIGGER {$order_insert_trigger} AFTER INSERT ON {$orders}
            FOR EACH ROW EXECUTE FUNCTION {$order_chain}()");
        $this->connection()->unprepared("CREATE TRIGGER {$order_update_trigger} AFTER UPDATE ON {$orders}
            FOR EACH ROW EXECUTE FUNCTION {$order_chain}()");

        $delivery_lines = $this->table(ERPTables::DeliveryNoteLines->value);
        $order_lines = $this->table(ERPTables::SalesOrderLines->value);
        $line_chain = $this->identifier('erp_lock_sales_order_line_chain');
        $this->connection()->unprepared("CREATE OR REPLACE FUNCTION {$line_chain}() RETURNS trigger AS $$
            BEGIN
                IF NEW.sales_order_line_id IS NOT NULL THEN
                    UPDATE {$order_lines} SET locked_at = COALESCE(locked_at, CURRENT_TIMESTAMP)
                        WHERE id = NEW.sales_order_line_id AND locked_at IS NULL;
                END IF;
                RETURN NEW;
            END;
        $$ LANGUAGE plpgsql");
        $delivery_insert_trigger = $this->identifier(ERPTables::DeliveryNoteLines->value . '_lock_chain_insert');
        $delivery_update_trigger = $this->identifier(ERPTables::DeliveryNoteLines->value . '_lock_chain_update');
        $this->connection()->unprepared("CREATE TRIGGER {$delivery_insert_trigger} AFTER INSERT ON {$delivery_lines}
            FOR EACH ROW EXECUTE FUNCTION {$line_chain}()");
        $this->connection()->unprepared("CREATE TRIGGER {$delivery_update_trigger} AFTER UPDATE ON {$delivery_lines}
            FOR EACH ROW EXECUTE FUNCTION {$line_chain}()");

        $line_guard = $this->identifier('erp_sales_order_line_guard');
        $this->connection()->unprepared("CREATE OR REPLACE FUNCTION {$line_guard}() RETURNS trigger AS $$
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
        $commercial_trigger = $this->identifier(ERPTables::SalesOrderLines->value . '_commercial_lock_guard_update');
        $delete_trigger = $this->identifier(ERPTables::SalesOrderLines->value . '_lock_guard_delete');
        $this->connection()->unprepared("CREATE TRIGGER {$commercial_trigger} BEFORE UPDATE ON {$order_lines}
            FOR EACH ROW EXECUTE FUNCTION {$line_guard}()");
        $this->connection()->unprepared("CREATE TRIGGER {$delete_trigger} BEFORE DELETE ON {$order_lines}
            FOR EACH ROW EXECUTE FUNCTION {$line_guard}()");
    }

    private function dropMysqlTriggers(): void
    {
        foreach (self::LOCKABLE_TABLES as $table) {
            $this->connection()->unprepared('DROP TRIGGER IF EXISTS ' . $this->identifier($table . '_lock_guard_update'));
            $this->connection()->unprepared('DROP TRIGGER IF EXISTS ' . $this->identifier($table . '_lock_guard_delete'));
        }

        foreach ($this->chainTriggerNames() as $trigger) {
            $this->connection()->unprepared('DROP TRIGGER IF EXISTS ' . $this->identifier($trigger));
        }
    }

    private function dropPostgresTriggers(): void
    {
        foreach (self::LOCKABLE_TABLES as $table) {
            $wrapped_table = $this->table($table);
            $update_trigger = $this->identifier($table . '_lock_guard_update');
            $delete_trigger = $this->identifier($table . '_lock_guard_delete');
            $this->connection()->unprepared("DROP TRIGGER IF EXISTS {$update_trigger} ON {$wrapped_table}");
            $this->connection()->unprepared("DROP TRIGGER IF EXISTS {$delete_trigger} ON {$wrapped_table}");
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
            $this->connection()->unprepared(sprintf(
                'DROP TRIGGER IF EXISTS %s ON %s',
                $this->identifier($trigger),
                $this->table($table),
            ));
        }

        $this->connection()->unprepared('DROP FUNCTION IF EXISTS ' . $this->identifier('erp_locked_record_guard') . '()');
        $this->connection()->unprepared('DROP FUNCTION IF EXISTS ' . $this->identifier('erp_lock_sales_order_chain') . '()');
        $this->connection()->unprepared('DROP FUNCTION IF EXISTS ' . $this->identifier('erp_lock_sales_order_line_chain') . '()');
        $this->connection()->unprepared('DROP FUNCTION IF EXISTS ' . $this->identifier('erp_sales_order_line_guard') . '()');
    }

    /**
     * @return list<string>
     */
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
        $orders = $this->table(ERPTables::SalesOrders->value);
        $quotations = $this->table(ERPTables::Quotations->value);
        $projects = $this->table(ERPTables::Projects->value);
        $trigger = $this->identifier(ERPTables::SalesOrders->value . '_lock_chain_' . $operation);

        return "CREATE TRIGGER {$trigger} AFTER {$operation} ON {$orders} FOR EACH ROW
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
        $delivery_lines = $this->table(ERPTables::DeliveryNoteLines->value);
        $order_lines = $this->table(ERPTables::SalesOrderLines->value);
        $trigger = $this->identifier(ERPTables::DeliveryNoteLines->value . '_lock_chain_' . $operation);

        return "CREATE TRIGGER {$trigger} AFTER {$operation} ON {$delivery_lines} FOR EACH ROW
            BEGIN
                IF NEW.sales_order_line_id IS NOT NULL THEN
                    UPDATE {$order_lines} SET locked_at = COALESCE(locked_at, CURRENT_TIMESTAMP)
                        WHERE id = NEW.sales_order_line_id AND locked_at IS NULL;
                END IF;
            END";
    }

    private function connection(): Connection
    {
        return $this->activeConnection ??= app('db')->connection();
    }

    private function table(string $table): string
    {
        return $this->connection()->getQueryGrammar()->wrapTable($table);
    }

    private function identifier(string $identifier): string
    {
        return $this->connection()->getQueryGrammar()->wrap($identifier);
    }
};
