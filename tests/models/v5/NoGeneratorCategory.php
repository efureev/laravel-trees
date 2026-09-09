<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\models\v5;

use Fureev\Trees\Config\Builder;

/**
 * A multi-tree model that declines to generate tree ids, so every root has to be given one.
 */
class NoGeneratorCategory extends AbstractModel
{
    protected $fillable = ['title'];

    protected $table = 'no_generator_categories';

    protected static function buildTree(): Builder
    {
        return Builder::defaultMulti();
    }

    protected function treeIdGenerator(): ?string
    {
        return null;
    }
}
