<?php

declare(strict_types=1);

namespace Modules\ERP\Tests\Stubs;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;

/**
 * SQLite ignores `FOR UPDATE`, so a test cannot see a row lock in the SQL it ran. This grammar
 * records, for every SELECT it compiles, the table, whether a lock was requested and the transaction
 * depth, then compiles exactly as SQLite does.
 */
final class LockRecordingSqliteGrammar extends SQLiteGrammar
{
    /**
     * @var list<array{table: string, locked: bool, depth: int}>
     */
    public array $selects = [];

    public function compileSelect(Builder $query): string
    {
        $this->selects[] = [
            'table' => is_string($query->from) ? $query->from : '',
            'locked' => (bool) $query->lock,
            'depth' => $this->connection->transactionLevel(),
        ];

        return parent::compileSelect($query);
    }
}
