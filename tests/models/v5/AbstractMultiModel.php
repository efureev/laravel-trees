<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\models\v5;

use Fureev\Trees\Config\Builder;

/**
 * @property int $tree_id
 */
abstract class AbstractMultiModel extends AbstractModel
{
    protected static function buildTree(): Builder
    {
        return Builder::defaultMulti();
    }
}
