<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\models\v5;

/**
 * Multi tree model that does not provide a tree-id generator, so saving a root
 * without an explicit tree value must raise {@see \Fureev\Trees\Exceptions\TreeNeedValueException}.
 *
 * @property string $title
 */
class MultiCategoryWithoutTreeGenerator extends AbstractMultiModel
{
    protected $fillable = ['title'];

    protected $table = 'categories_multi';

    protected function treeIdGenerator(): ?string
    {
        return null;
    }
}
