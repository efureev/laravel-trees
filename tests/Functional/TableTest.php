<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional;

use Fureev\Trees\Table;
use Fureev\Trees\Tests\models\Structure;
use Fureev\Trees\Tests\models\v5\Category;
use Illuminate\Console\BufferedConsoleOutput;
use PHPUnit\Framework\Attributes\Test;

class TableTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    #[Test]
    public function drawFromModel(): void
    {
        /** @var Category $root */
        $root = static::model(['title' => 'root node']);
        $root->makeRoot()->save();

        /** @var Category $node1 */
        $node1 = static::model(['title' => 'node 1']);
        $node1->appendTo($root)->save();

        /** @var Category $node21 */
        $node21 = static::model(['title' => 'child 2.1']);
        $node21->appendTo($node1)->save();

        /** @var Category $node22 */
        $node22 = static::model(['title' => 'child 3.1']);
        $node22->appendTo($node21)->save();

        /** @var Category $node32 */
        $node32 = static::model(['title' => 'child 2.2']);
        $node32->appendTo($node1)->save();

        //

        $output = new BufferedConsoleOutput();
        Table::fromModel($root->refresh())
            ->setExtraColumns(
                ['title' => 'Label']
            )
            ->draw($output);

        $str = $output->fetch();

        self::assertNotEmpty($str);
    }
//
//    #[Test]
//    public function drawFromTree(): void
//    {
//        $root        = $this->createStructure();
//        $structure1  = $this->createStructure($root);
//        $structure2  = $this->createStructure($root);
//        $structure11 = $this->createStructure($structure1);
//        $structure12 = $this->createStructure($structure11);
//
//        $output = new BufferedConsoleOutput();
//
//        $collection = Structure::all();
//
//        Table::fromTree($collection->toTree())
//            ->hideLevel()
//            ->setExtraColumns(
//                [
//                    'title'                         => 'Label',
//                    $root->leftAttribute()->name()  => 'Left',
//                    $root->rightAttribute()->name() => 'Right',
//                    $root->levelAttribute()->name() => 'Deep',
//                ]
//            )
//            ->draw($output);
//
//        $str = $output->fetch();
//
//        self::assertNotEmpty($str);
//    }

//
//    #[Test]
//    public function drawFromCollection(): void
//    {
//        $root        = $this->createStructure();
//        $structure1  = $this->createStructure($root);
//        $structure2  = $this->createStructure($root);
//        $structure11 = $this->createStructure($structure1);
//        $structure12 = $this->createStructure($structure11);
//
//        $output = new BufferedConsoleOutput();
//
//        $collection = Structure::all();
//        $collection
//            ->toOutput(
//                [
//                    'title'                         => 'Label',
//                    $root->leftAttribute()->name()  => 'Left',
//                    $root->rightAttribute()->name() => 'Right',
//                ],
//                $output,
//                '...'
//            );
//
//        $str = $output->fetch();
//
//        self::assertNotEmpty($str);
//    }
}
