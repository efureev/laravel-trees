<?php

include __DIR__ . '/../vendor/autoload.php';
include __DIR__ . '/models/Category.php';

$capsule = new \Illuminate\Database\Capsule\Manager;
$capsule->addConnection([
    'driver' => env('DB_DRIVER', 'sqlite'),
    'database' => env('DB_DATABASE', ':memory:'),
    'username' => env('DB_USERNAME'),
    'host' => env('DB_HOST', 'localhost'),
    'prefix' => 'prfx_',
]);

//$capsule->addConnection(['driver' => 'pgsql', 'database' => 'test', 'username' => 'efureev', 'host' => 'localhost', 'prefix' => 'tree_']);
$capsule->setEventDispatcher(new \Illuminate\Events\Dispatcher);
$capsule->bootEloquent();

$capsule->setAsGlobal();
