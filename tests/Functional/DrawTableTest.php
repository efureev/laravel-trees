<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional;

use Fureev\Trees\Table;
use Fureev\Trees\Tests\Functional\Helpers\InstallMigration;
use Fureev\Trees\Tests\Functional\Helpers\StructureHelper;
use Fureev\Trees\Tests\models\Structure;
use Illuminate\Console\BufferedConsoleOutput;

class DrawTableTest extends AbstractFunctionalTestCase
{
    use StructureHelper;

    protected function setUp(): void
    {
        parent::setUp();

        (new InstallMigration(Structure::class))->install();
    }


    /**
     * @test
     */
    public function drawFromModel(): void
    {
        $root        = $this->createStructure();
        $structure1  = $this->createStructure($root);
        $structure2  = $this->createStructure($root);
        $structure11 = $this->createStructure($structure1);
        $structure12 = $this->createStructure($structure11);

        $output = new BufferedConsoleOutput();
        Table::fromModel($root->refresh())
            ->setExtraColumns(
                [
                    'title' => 'Label',
                ]
            )
            ->draw($output);

        $str = $output->fetch();

        self::assertNotEmpty($str);
    }

    /**
     * @test
     */
    public function drawFromTree(): void
    {
        $root        = $this->createStructure();
        $structure1  = $this->createStructure($root);
        $structure2  = $this->createStructure($root);
        $structure11 = $this->createStructure($structure1);
        $structure12 = $this->createStructure($structure11);

        $output = new BufferedConsoleOutput();

        $collection = Structure::all();

        Table::fromTree($collection->toTree())
            ->hideLevel()
            ->setExtraColumns(
                [
                    'title'                         => 'Label',
                    $root->leftAttribute()->name()  => 'Left',
                    $root->rightAttribute()->name() => 'Right',
                    $root->levelAttribute()->name() => 'Deep',
                ]
            )
            ->draw($output);

        $str = $output->fetch();

        self::assertNotEmpty($str);
    }


    /**
     * @test
     */
    public function drawFromCollection(): void
    {
        $root        = $this->createStructure();
        $structure1  = $this->createStructure($root);
        $structure2  = $this->createStructure($root);
        $structure11 = $this->createStructure($structure1);
        $structure12 = $this->createStructure($structure11);

        $output = new BufferedConsoleOutput();

        $collection = Structure::all();
        $collection
            ->toOutput(
                [
                    'title'                         => 'Label',
                    $root->leftAttribute()->name()  => 'Left',
                    $root->rightAttribute()->name() => 'Right',
                ],
                $output,
                '...'
            );

        $str = $output->fetch();

        self::assertNotEmpty($str);
    }
}
