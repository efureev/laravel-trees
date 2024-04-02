<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\Concerns\InteractsWithDatabase;
use Orchestra\Testbench\TestCase;

abstract class AbstractFunctionalTestCase extends TestCase
{
    use InteractsWithDatabase;

    /**
     * Define environment setup.
     *
     * @param Application $app
     *
     * @return void
     */
    protected function getEnvironmentSetUp($app): void
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', env('DATABASE_DEFAULT', 'pgsql'));
        $app['config']->set(
            'database.connections.pgsql',
            [
                'driver'         => 'pgsql',
                'url'            => env('DATABASE_URL'),
                'host'           => env('DB_HOST', 'localhost'),
                'port'           => env('DB_PORT', '5432'),
                'database'       => env('DB_DATABASE', 'postgres'),
                'username'       => env('DB_USERNAME', 'postgres'),
                'password'       => env('DB_PASSWORD', 'postgres'),
                'charset'        => 'utf8',
                'prefix'         => '',
                'prefix_indexes' => true,
                'schema'         => 'public',
                'sslmode'        => 'prefer',
            ]
        );

        $app['config']->set(
            'database.connections.mysql',
            [
                'driver'         => 'mysql',
                'url'            => env('DATABASE_URL'),
                'host'           => env('DB_HOST', '127.0.0.1'),
                'port'           => env('DB_PORT', '3306'),
                'database'       => env('DB_DATABASE', 'forge'),
                'username'       => env('DB_USERNAME', 'forge'),
                'password'       => env('DB_PASSWORD', 'forge'),
                'unix_socket'    => env('DB_SOCKET', ''),
                'charset'        => 'utf8mb4',
                'collation'      => 'utf8mb4_unicode_ci',
                'prefix'         => '',
                'prefix_indexes' => true,
                'strict'         => true,
                'engine'         => null,
                'options'        => extension_loaded('pdo_mysql') ? array_filter([
                    \PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                ]) : [],
            ]
        );
    }

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:wipe');
    }
}
