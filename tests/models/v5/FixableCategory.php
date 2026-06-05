<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\models\v5;

/**
 * Single tree model whose query builder supports the fixing helpers.
 *
 * @property string $title
 */
class FixableCategory extends AbstractModel
{
    protected $fillable = ['title'];

    protected $table = 'categories';

    public function newEloquentBuilder($query): FixingQueryBuilder
    {
        return new FixingQueryBuilder($query);
    }
}
