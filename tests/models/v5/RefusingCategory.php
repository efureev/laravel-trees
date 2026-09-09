<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\models\v5;

use Fureev\Trees\Exceptions\DeletedNodeHasChildrenException;

/**
 * Overrides the `onDeletingNodeHasChildren()` hook to refuse the delete rather than let the
 * children be re-parented.
 */
class RefusingCategory extends Category
{
    protected function onDeletingNodeHasChildren(): void
    {
        throw new DeletedNodeHasChildrenException($this);
    }
}
