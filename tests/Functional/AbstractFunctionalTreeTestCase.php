<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional;

use Fureev\Trees\Database\Migrate;
use Fureev\Trees\Tests\models\v5\AbstractModel;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

abstract class AbstractFunctionalTreeTestCase extends AbstractFunctionalTestCase
{
    /**
     * @return class-string<AbstractModel>
     */
    abstract protected static function modelClass(): string;

    protected static function model(array $attributes = []): Model
    {
        return instance(static::modelClass(), $attributes);
    }

    private static function dbMigrate(): void
    {
        /** @var ConnectionInterface $connection */
        $connection = app('db.connection');

        $connectionDriver = $connection->getDriverName();
        if ($connectionDriver === 'pgsql') {
            app('db.connection')->statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp";');
        }

        /** @var AbstractModel $model */
        $model = static::model();

        $treeBuilder = $model->getTreeBuilder();

        $connection->getSchemaBuilder()->create(
            $model->getTable(),
            static function (Blueprint $table) use ($treeBuilder, $model, $connectionDriver) {
                $expression = match ($connectionDriver) {
                    'pgsql' => new Expression('uuid_generate_v4()'),
                    'mysql' => new Expression('UUID()'),
                    default => throw new \Exception('Your DB driver [' . DB::getDriverName() . '] does not supported'),
                };


                if (in_array($model->getKeyType(), ['uuid', 'string'])) {
                    $table->uuid($model->getKeyName())->default($expression)->primary();
                } elseif ($model->getKeyType() === 'ulid') {
                    $table->ulid($model->getKeyName())->primary();
                } else {
                    $table->integerIncrements($model->getKeyName());
                }

                (new Migrate($treeBuilder, $table))->buildColumns();

                $table->string('title');
                $table->string('path')->nullable();
                $table->json('params')->default('{}');

                if (method_exists($model, 'isSoftDelete') && $model::isSoftDelete()) {
                    $table->softDeletes();
                }
            }
        );

        $connection->enableQueryLog();
    }

    public function setUp(): void
    {
        parent::setUp();
        static::dbMigrate();
    }
}
