<?php

namespace Fureev\Trees\Tests\Unit;

use Faker\Provider\Uuid;
use Fureev\Trees\Tests\models\Structure;

class FixMultiTreeTest extends AbstractUnitTestCase
{
    protected static $modelClass = Structure::class;

    public function testFixWithoutErrors(): void
    {
        static::buildMultiTree();

        static::assertFalse(static::$modelClass::isBroken());
        static::assertEquals(0, array_sum(Structure::fixMultiTree()));
    }

    public function testFixWithErrors(): void
    {
        static::buildMultiTree();

        /** @var Structure $brokenModel */
        $brokenModel = static::$modelClass::root()->first()->children()->first();

        $brokenModel
            ->setAttribute($brokenModel->rightAttribute()->name(), 4)
            ->setAttribute($brokenModel->levelAttribute()->name(), 4);

        $brokenModel->save();

        static::assertEquals(1, static::$modelClass::countErrors('oddness'));
        static::assertEquals(2, static::$modelClass::countErrors('duplicates'));

        Structure::fixMultiTree();
        static::assertEquals(1, $brokenModel->fresh()->levelValue());

        static::assertEquals(0, static::$modelClass::countErrors('oddness'));
    }


    public static function buildMultiTree()
    {
        $trees = [];
        for ($i = 1; $i <= 1; $i++) {
            $trees[] = $uuid = Uuid::uuid();

            $root = new static::$modelClass(
                [
                    'title'   => "Root structure site: #$i [$uuid]",
                    'tree_id' => $uuid,
                    'path'    => [0],
                ]
            );

            $root->save();

            foreach (['en', 'ru'] as $local) {
                $path   = $root->path;
                $path[] = $local;
                /** @var Structure $node */
                $node = new static::$modelClass(
                    [
                        'title'  => "[$local] Locale structure",
                        'path'   => $path,
                        'params' => ['local' => $local],
                    ]
                );

                $node->appendTo($root);
                $node->save();

                $subNode = new static::$modelClass(
                    [
                        'title' => "subnode",
                    ]
                );

                $subNode->appendTo($node);
                $subNode->save();
            }
        }

        return $trees;
    }

}
