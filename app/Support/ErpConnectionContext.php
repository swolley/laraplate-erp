<?php

declare(strict_types=1);

namespace Modules\ERP\Support;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final readonly class ErpConnectionContext
{
    /**
     * Instantiate a trusted model class and apply its configured connection.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model_class
     * @return TModel
     */
    public function model(string $model_class): Model
    {
        return $this->resolve(new $model_class);
    }

    /**
     * Resolve a model prototype using trusted application configuration.
     *
     * @template TModel of Model
     *
     * @param  TModel  $prototype
     * @return TModel
     */
    public function resolve(Model $prototype): Model
    {
        $model_connections = config('erp.model_connections', []);

        if (! is_array($model_connections)) {
            throw new LogicException('ERP model connection configuration must be an array.');
        }

        $connection_name = $model_connections[$prototype::class]
            ?? $model_connections[$prototype->getTable()]
            ?? null;

        if ($connection_name === null) {
            return $prototype;
        }

        $connections = config('database.connections', []);

        if (! is_string($connection_name)
            || $connection_name === ''
            || ! is_array($connections)
            || ! array_key_exists($connection_name, $connections)) {
            throw new LogicException('ERP model connection is not configured.');
        }

        return $prototype->setConnection($connection_name);
    }
}
