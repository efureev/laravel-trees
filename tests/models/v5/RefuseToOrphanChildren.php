<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\models\v5;

use Fureev\Trees\Exceptions\DeletedNodeHasChildrenException;
use Fureev\Trees\Strategy\ChildrenHandler;
use Illuminate\Database\Eloquent\Model;

/**
 * Alternative to MoveChildrenToParent: refuse the delete instead of re-parenting.
 */
class RefuseToOrphanChildren implements ChildrenHandler
{
    public function handle(Model $model): void
    {
        throw new DeletedNodeHasChildrenException($model);
    }
}
