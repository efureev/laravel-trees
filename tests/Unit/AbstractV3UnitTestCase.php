<?php

namespace Fureev\Trees\Tests\Unit;

use Fureev\Trees\Tests\AbstractTestCase;
use Fureev\Trees\Tests\models\BaseModel;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

/**
 * @deprecated
 */
abstract class AbstractV3UnitTestCase extends AbstractTestCase
{
    /** @var string */
    protected static $modelClassTable;

    /**
     * @var BaseModel
     */
    protected static $modelClass;

    private static function setUpDb()
    {
        /** @var ConnectionInterface $connection */
        $connection = app('db.connection');

        $connectionDriver = $connection->getDriverName();
        if ($connectionDriver === 'pgsql') {
            app('db.connection')->statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp";');
        }

        /** @var BaseModel $model */
        $model = new static::$modelClass;

        $config = $model->getTreeConfig();

        $connection->getSchemaBuilder()->create(
            $model->getTable(),
            static function (Blueprint $table) use ($config, $model, $connectionDriver) {
                switch ($connectionDriver) {
                    case 'pgsql':
                        $expression = new Expression('uuid_generate_v4()');
                        break;
                    case 'mysql':
                        $expression = new Expression('UUID()');
                        break;
                    default:
                        throw new \Exception('Your DB driver [' . DB::getDriverName() . '] does not supported');
                        break;
                }


                if (in_array($model->getKeyType(), ['uuid', 'string'])) {
                    $table->uuid($model->getKeyName())->default($expression)->primary();
                } else {
                    $table->integerIncrements($model->getKeyName());
                }

                Migrate::columns($table, $config);
                $table->string('title');
                $table->string('path')->nullable();
                $table->json('params')->default('{}');
                if ($model::isSoftDelete()) {
                    $table->softDeletes();
                }
            }
        );

        $connection->enableQueryLog();
    }

    public function setUp(): void
    {
        parent::setUp();
        static::setUpDb();
    }

    /**
     * Helper for creating a tree
     *
     * @param BaseModel|null $parentNode
     * @param mixed ...$childrenNodesCount
     */
    protected static function makeTree($parentNode = null, ...$childrenNodesCount): void
    {
        if (!count($childrenNodesCount)) {
            return;
        }

        $childrenCount = array_shift($childrenNodesCount);

        for ($i = 1; $i <= $childrenCount; $i++) {
            if (!$parentNode) {
                /** @var BaseModel $node */
                $node = new static::$modelClass(
                    [
                        '_setRoot' => true,
                        'title'    => "Root node $i",
                        'params'   => ['seo' => ['title' => "SEO: root node $i"]],
                    ]
                );
                $path = [$i];
            } else {
                $path    = $parentNode->path;
                $path[]  = $i;
                $pathStr = implode('.', $path);

                /** @var BaseModel $node */
                $node = new static::$modelClass(['title' => "child $pathStr"]);
                $node->prependTo($parentNode);
            }

            $node->path = $path;
            $node->save();

            static::makeTree($node, ...$childrenNodesCount);
        }
    }

    /**
     * @return \Fureev\Trees\Tests\models\BaseModel
     */
    protected static function createRoot(): BaseModel
    {
        $model = new static::$modelClass(['title' => 'root node']);

        $model->makeRoot()->save();

        return $model;
    }

    protected static function sum(array $childMap, $level = null): int
    {
        if (!count($childMap)) {
            return 0;
        }

        if ($level === null) {
            $level = count($childMap);
        }

        $childMap = array_slice($childMap, 0, $level + 1);

        $res = array_reduce(
            $childMap,
            static function ($prev, $next) {
                if (!$prev) {
                    return [$next, $next];
                }

                [$prevCount, $total] = $prev;
                $prevTotal = $prevCount * $next;
                $total     = $prevTotal + $total;

                return [$prevTotal, $total];
            }
        );

        return $res[1];
    }

}
