<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Concerns;

/**
 * Counting the statements one operation costs, for the tests that pin the numbers written in
 * the documentation.
 */
trait CountsStatements
{
    /**
     * The log is cleared first, so whatever the fixture cost stays out of the count.
     */
    private function statements(callable $operation): int
    {
        $connection = static::model()->getConnection();

        $connection->flushQueryLog();
        $operation();

        return count($connection->getQueryLog());
    }
}
