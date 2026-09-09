<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Uno;

use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\Category;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

/**
 * A node may live on a connection other than the default one — `Model::on()`, `setConnection()`,
 * a tenant connection, a write connection behind a read/write split. Every statement the package
 * issues for it has to go there.
 *
 * The bound shift used to be the exception. It reached for its query through `Model::query()`,
 * which is static: it builds a fresh instance, and a fresh instance carries the default
 * connection. The select and the insert went to the node's connection while the statement that
 * rewrites the bounds of the whole tree went somewhere else.
 */
class ConnectionTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<Category>
     */
    protected static function modelClass(): string
    {
        return Category::class;
    }

    public function setUp(): void
    {
        parent::setUp();

        config(['database.connections.other' => config('database.connections.pgsql')]);
    }

    protected function tearDown(): void
    {
        DB::purge('other');

        parent::tearDown();
    }

    /**
     * @return string[]
     */
    private function loggedOn(string $connection): array
    {
        return array_map(
            static fn(array $q): string => (string)$q['query'],
            DB::connection($connection)->getQueryLog()
        );
    }

    #[Test]
    public function theShiftRunsOnTheModelsOwnConnection(): void
    {
        /** @var Category $root */
        $root = Category::on('other')->getModel()->newInstance(['title' => 'root']);
        $root->setConnection('other');
        $root->makeRoot()->save();

        /** @var Category $child */
        $child = new Category(['title' => 'child']);
        $child->setConnection('other');
        $child->appendTo($root->refresh())->save();

        DB::connection()->flushQueryLog();
        DB::connection('other')->enableQueryLog();
        DB::connection('other')->flushQueryLog();

        /** @var Category $second */
        $second = new Category(['title' => 'second']);
        $second->setConnection('other');
        $second->prependTo($root->refresh())->save();

        $own = implode(' | ', $this->loggedOn('other'));

        static::assertStringContainsString('update "categories" set', $own);
        static::assertStringContainsString('"lft"', $own);

        // Nothing leaked onto the default connection.
        static::assertSame([], $this->loggedOn('pgsql'));
    }

    #[Test]
    public function theTreeIsBuiltCorrectlyOnAnotherConnection(): void
    {
        /** @var Category $root */
        $root = new Category(['title' => 'root']);
        $root->setConnection('other');
        $root->makeRoot()->save();

        foreach (['a', 'b'] as $title) {
            /** @var Category $node */
            $node = new Category(['title' => $title]);
            $node->setConnection('other');
            $node->appendTo($root->refresh())->save();
        }

        static::assertSame(
            [
                'root',
                'a',
                'b',
            ],
            Category::on('other')->defaultOrder()->get()->pluck('title')->all()
        );

        static::assertSame([1, 6], [$root->refresh()->leftValue(), $root->rightValue()]);
    }
}
