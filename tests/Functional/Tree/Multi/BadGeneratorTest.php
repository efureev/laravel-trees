<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Tree\Multi;

use Fureev\Trees\Exceptions\Exception;
use Fureev\Trees\Tests\Functional\AbstractFunctionalTreeTestCase;
use Fureev\Trees\Tests\models\v5\BadGeneratorCategory;
use PHPUnit\Framework\Attributes\Test;

/**
 * The generator is named by class, so whether it is one is only found when it is built.
 */
class BadGeneratorTest extends AbstractFunctionalTreeTestCase
{
    /**
     * @return class-string<BadGeneratorCategory>
     */
    protected static function modelClass(): string
    {
        return BadGeneratorCategory::class;
    }

    #[Test]
    public function aGeneratorThatIsNotOneIsRefused(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid Generator');

        static::model(['title' => 'root'])->makeRoot()->save();
    }
}
