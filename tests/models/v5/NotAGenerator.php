<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\models\v5;

/**
 * Constructible, but not a tree id generator. Used to reach the check that refuses one.
 */
class NotAGenerator
{
    public function __construct(mixed ...$arguments)
    {
    }
}
