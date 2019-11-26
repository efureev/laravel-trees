<?php

use Illuminate\Database\Capsule\Manager;
use Illuminate\Events\Dispatcher;

include __DIR__ . '/../vendor/autoload.php';
include __DIR__ . '/models/Category.php';

$capsule = new Manager;
$capsule->addConnection([
    'driver' => env('DB_DRIVER', 'pgsql'),
    'database' => env('DB_DATABASE', 'travis_ci_test'),
    'username' => env('DB_USERNAME', 'postgres'),
    'password' => env('DB_PASSWORD', ''),
    'host' => env('DB_HOST', 'localhost'),
    'prefix' => 'prfx_',
]);

$capsule->setEventDispatcher(new Dispatcher);
$capsule->bootEloquent();

$capsule->setAsGlobal();
