<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\models\v5;

use Fureev\Trees\Config\Builder;

/**
 * Configured with class names that are not strategies at all, to reach the two checks that
 * refuse them.
 */
class BrokenStrategyCategory extends AbstractModel
{
    protected $fillable = ['title'];

    protected $table = 'broken_strategy_categories';

    protected static function buildTree(): Builder
    {
        return Builder::default()
            ->setDeleterWithChildren(NonTreeModel::class)
            ->setChildrenHandlerOnDelete(NonTreeModel::class);
    }
}
