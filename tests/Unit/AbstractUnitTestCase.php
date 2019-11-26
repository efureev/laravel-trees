<?php

namespace Fureev\Trees\Tests\Unit;

use Fureev\Trees\Migrate;
use Fureev\Trees\Tests\AbstractTestCase;
use Fureev\Trees\Tests\models\BaseModel;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

abstract class AbstractUnitTestCase extends AbstractTestCase
{
    /** @var string */
    protected static $modelClassTable;

    /**
     * @var BaseModel
     */
    protected static $modelClass;

    public static function setUpBeforeClass(): void
    {
        /** @var BaseModel $model */
        $model = new static::$modelClass;
        self::$modelClassTable = $model->getTable();

        $schema = Capsule::schema();

        $schema->dropIfExists(self::$modelClassTable);
        Capsule::disableQueryLog();

        $config = $model->getTreeConfig();

        $connectionDriver = $schema->getConnection()->getDriverName();

        if ($connectionDriver === 'pgsql') {
            $schema->getConnection()->statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp";');
        }

        $schema->create(self::$modelClassTable, static function (Blueprint $table) use ($config, $model, $connectionDriver) {

            switch ($connectionDriver) {
                case 'pgsql':
                    $expression = new Expression('uuid_generate_v4()');
                    break;
                case 'mysql':
                    $expression = new Expression('UUID()');
                    break;
                case 'sqlite':
                    $expression = new Expression('randomblob(16)');
                    break;
                default:
                    throw new \Exception('Your DB driver [' . DB::getDriverName() . '] does not supported');
                    break;
            }


            if ($model->getKeyType() === 'uuid') {
                $table->uuid('id')->default($expression)->primary();
            } else {
                $table->integerIncrements('id');
            }

            Migrate::getColumns($table, $config);
            $table->string('title');
            $table->string('path')->nullable();
            if ($model::isSoftDelete()) {
                $table->softDeletes();
            }

        });

        Capsule::enableQueryLog();
    }

    public function setUp(): void
    {
        Capsule::flushQueryLog();
        date_default_timezone_set('Europe/Moscow');
    }

    public function tearDown(): void
    {
        Capsule::table(self::$modelClassTable)->truncate();
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
                $node = new static::$modelClass([
                    '_setRoot' => true,
                    'title' => "Root node $i",
                    'params' => ['seo' => ['title' => "SEO: root node $i"]],
                ]);
                $path = [$i];
            } else {
                $path = $parentNode->path;
                $path[] = $i;
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

}
