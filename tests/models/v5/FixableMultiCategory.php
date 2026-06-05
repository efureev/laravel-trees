<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\models\v5;

/**
 * Multi tree model whose query builder supports the fixing helpers.
 *
 * @property string $title
 * @property int $tree_id
 */
class FixableMultiCategory extends AbstractMultiModel
{
    protected $fillable = ['title'];

    protected $table = 'categories_multi';

    public function newEloquentBuilder($query): FixingQueryBuilder
    {
        return new FixingQueryBuilder($query);
    }
}
