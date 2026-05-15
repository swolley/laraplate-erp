<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\ERP\Enums\ERPTables;

/**
 * Installs BEFORE UPDATE and BEFORE DELETE triggers on lockable tables
 * to prevent modification of locked records at the database level.
 *
 * These triggers act as a safety net complementing the application-level
 * HasLocks trait and observers. They block raw SQL updates or deletes
 * that bypass Eloquent.
 *
 * An admin can still unlock a record by setting locked_at = NULL.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const array LOCKABLE_TABLES = [ERPTables::Quotations->value, ERPTables::SalesOrders->value];

    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $this->installMysqlTriggers();
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            foreach (self::LOCKABLE_TABLES as $table) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_lock_guard_update");
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_lock_guard_delete");
            }
        }
    }

    private function installMysqlTriggers(): void
    {
        foreach (self::LOCKABLE_TABLES as $table) {
            DB::unprepared("
                CREATE TRIGGER {$table}_lock_guard_update
                BEFORE UPDATE ON {$table}
                FOR EACH ROW
                BEGIN
                    IF OLD.locked_at IS NOT NULL AND (NEW.locked_at IS NOT NULL AND NEW.locked_at = OLD.locked_at) THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Cannot modify a locked record. Unlock it first by setting locked_at to NULL.';
                    END IF;
                END
            ");

            DB::unprepared("
                CREATE TRIGGER {$table}_lock_guard_delete
                BEFORE DELETE ON {$table}
                FOR EACH ROW
                BEGIN
                    IF OLD.locked_at IS NOT NULL THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Cannot delete a locked record. Unlock it first by setting locked_at to NULL.';
                    END IF;
                END
            ");
        }
    }
};
