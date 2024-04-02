<?php

declare(strict_types=1);

namespace Fureev\Trees\Strategy;

use Fureev\Trees\UseTree;
use Illuminate\Database\Eloquent\Model;

interface ChildrenHandler
{
    /**
     * @param Model|UseTree $model
     */
    public function handle(Model $model): void;
}
