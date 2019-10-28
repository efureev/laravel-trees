<?php

namespace Fureev\Trees;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ServiceProvider;

/**
 * Class NestedSetServiceProvider
 *
 * @package Fureev\Trees
 */
class NestedSetServiceProvider extends ServiceProvider
{
    /**
     *
     */
    public function register(): void
    {
        Blueprint::macro('installNestedSet', static function ($instance) {
            Config::getColumns($instance);
        });

        Blueprint::macro('dropNestedSet', static function ($instance) {
            Config::dropColumns($instance);
        });
    }
}
