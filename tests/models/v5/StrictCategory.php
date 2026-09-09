<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\models\v5;

use Fureev\Trees\Config\Builder;

/**
 * A model that refuses to delete a node which still has children, instead of pulling them up.
 * Used to show that the delete behaviour is a replaceable strategy.
 */
class StrictCategory extends AbstractModel
{
    protected $fillable = ['title'];

    protected $table = 'strict_categories';

    protected static function buildTree(): Builder
    {
        return Builder::default()
            ->setChildrenHandlerOnDelete(RefuseToOrphanChildren::class);
    }
}
