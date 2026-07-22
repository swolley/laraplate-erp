<?php

declare(strict_types=1);

namespace Modules\ERP\Support;

use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class ConnectionScopedTransaction
{
    /**
     * Execute a transaction on the aggregate root connection.
     *
     * @template TReturn
     *
     * @param  Closure(ConnectionScopedModels): TReturn  $callback
     * @return TReturn
     */
    public static function run(Model $root, Closure $callback, Model ...$related): mixed
    {
        $connection = self::connection($root, ...$related);
        $models = ConnectionScopedModels::for($root, ...$related);

        return $connection->transaction(fn (): mixed => $callback($models));
    }

    /**
     * Resolve the aggregate root connection and reject cross-connection writes.
     */
    public static function connection(Model $root, Model ...$related): ConnectionInterface
    {
        $connection = $root->getConnection();
        $connection_name = $connection->getName();

        foreach ($related as $model) {
            if ($connection_name !== $model->getConnection()->getName()) {
                throw new LogicException('ERP aggregates must use the same database connection.');
            }
        }

        return $connection;
    }
}
