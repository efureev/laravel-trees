<?php

declare(strict_types=1);

namespace Fureev\Trees\Strategy;

use Fureev\Trees\UseTree;
use Illuminate\Database\Eloquent\Model;

class MoveChildrenToParent implements ChildrenHandler
{
    /**
     * @param Model|UseTree $model
     */
    public function handle(Model $model): void
    {
        $model->moveChildrenToParent();
    }
}
