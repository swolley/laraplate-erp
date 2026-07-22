<?php

declare(strict_types=1);

namespace Modules\ERP\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class ConnectionScopedModels
{
    /**
     * @var array<class-string<Model>, string>
     */
    private array $explicit_connections = [];

    private function __construct(private readonly string $connection_name) {}

    public static function for(Model $root, Model ...$participants): self
    {
        $models = new self((string) $root->getConnection()->getName());

        foreach ($participants as $participant) {
            $models->rememberParticipant($participant);
        }

        return $models;
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model_class
     * @return TModel
     */
    public function model(string $model_class): Model
    {
        $model = new $model_class;
        $explicit_connection = $model->getConnectionName();

        if ($explicit_connection !== null && $explicit_connection !== $this->connection_name) {
            throw new LogicException('ERP participants must use the aggregate database connection.');
        }

        if (isset($this->explicit_connections[$model_class])
            && $this->explicit_connections[$model_class] !== $this->connection_name) {
            throw new LogicException('ERP participants must use the aggregate database connection.');
        }

        return $model->setConnection($this->connection_name);
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model_class
     * @return Builder<TModel>
     */
    public function query(string $model_class): Builder
    {
        return $this->model($model_class)->newQuery();
    }

    private function rememberParticipant(Model $participant): void
    {
        $connection_name = $participant->getConnectionName();

        if ($connection_name !== null) {
            $this->explicit_connections[$participant::class] = $connection_name;
        }
    }
}
