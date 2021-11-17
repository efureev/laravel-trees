<?php

namespace Fureev\Trees\Tests\Functional\Helpers;

use Fureev\Trees\Migrate;
use Fureev\Trees\NestedSetTrait;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;

class InstallMigration
{
    /** @var Model|NestedSetTrait */
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

    private static function connection(): PostgresConnection
    {
        return app('db.connection');
    }


    public function install(): void
    {
        $config = $this->model->getTreeConfig();

        $this->connection->getSchemaBuilder()->create(
            $this->model->getTable(),
            function (Blueprint $table) use ($config) {
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

                Migrate::columns($table, $config);
                $cols = array_diff_key($this->model->getCasts(), array_flip($config->columns()));
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
