<?php

namespace Fureev\Trees;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ServiceProvider;

class NestedSetServiceProvider extends ServiceProvider
{
    public function register()
    {
        Blueprint::macro('installNestedSet', function () {
            NestedSetConfig::getColumns($this);
        });
        Blueprint::macro('dropNestedSet', function () {
            NestedSetConfig::dropColumns($this);
        });
    }
}
