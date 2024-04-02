<?php

namespace Fureev\Trees\Tests\Functional\Helpers;

use Fureev\Trees\Config\Builder;
use Fureev\Trees\Database\Migrate;
use Fureev\Trees\UseTree;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;

class InstallMigration
{
    /** @var Model|UseTree */
    private Model $model;

    private ConnectionInterface $connection;

    public function __construct(Model|string $model)
    {
        $this->model = instance($model);

        $this->prepare();
    }

    private function prepare(): void
    {
        $this->connection = self::connection();

        $connectionDriver = $this->connection->getDriverName();
        if ($connectionDriver === 'pgsql') {
            $this->connection->statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp";');
        }
    }

    private static function connection(): ConnectionInterface
    {
        return app('db.connection');
    }


    public function install(): void
    {
        /** @var Builder $builder */
        $builder = $this->model->getTreeBuilder();

        $this->connection->getSchemaBuilder()->create(
            $this->model->getTable(),
            function (Blueprint $table) use ($builder) {
                $driverName = $this->connection->getDriverName();
                $expression = match ($this->connection->getDriverName()) {
                    'pgsql' => new Expression('uuid_generate_v4()'),
                    'mysql' => new Expression('UUID()'),
                    default => throw new \Exception("Your DB driver [$driverName] does not supported"),
                };

                if ($this->model->getKeyType() === 'string') {
                    $table->uuid($this->model->getKeyName())->default($expression)->primary();
                } else {
                    $table->integerIncrements($this->model->getKeyName());
                }

                (new Migrate($builder, $table))->buildColumns();

                $cols = array_diff_key($this->model->getCasts(), array_flip($builder->columnsNames()));
                unset($cols['id']);

                foreach ($cols as $col => $type) {
                    match ($type) {
                        'array' || 'json' => $table->json($col)->default('{}'),
                        default => $table->string($col)->nullable(),
                    };
                }
            }
        );

        $this->connection->enableQueryLog();
    }

    public function enableQueryLog(): self
    {
        $this->connection->enableQueryLog();

        return $this;
    }

    public function disableQueryLog(): self
    {
        $this->connection->disableQueryLog();

        return $this;
    }
}
