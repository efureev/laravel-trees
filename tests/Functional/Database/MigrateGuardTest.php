<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Database;

use Fureev\Trees\Database\Migrate;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use Fureev\Trees\Tests\models\v5\NonTreeModel;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\Test;
use Fureev\Trees\Exceptions\InvalidConfigException;

/**
 * `columnsFromModel()` takes a class name, so the only moment it can tell a tree model from
 * anything else is when it looks for the builder.
 */
class MigrateGuardTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    #[Test]
    public function aModelWithoutATreeIsRefused(): void
    {
        $this->expectException(InvalidConfigException::class);

        Migrate::columnsFromModel(
            new Blueprint(app('db.connection'), 'whatever'),
            NonTreeModel::class
        );
    }
}
