<?php

declare(strict_types=1);

namespace Fureev\Trees\Strategy;

use Fureev\Trees\Contracts\TreeModel;
use Illuminate\Database\Eloquent\Model;

interface ChildrenHandler
{
    /**
     * @param Model&TreeModel $model
     */
    public function handle(Model $model): void;
}
