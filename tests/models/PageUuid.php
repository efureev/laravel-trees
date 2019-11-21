<?php

namespace Fureev\Trees\Tests\models;

use Fureev\Trees\Config;

/**
 * Class Page
 *
 * @package Fureev\Trees\Tests\models
 * @property int $id
 * @property string $title
 * @property int $lvl
 * @property int $tree_id
 * @property int $lft
 * @property int $rgt
 * @mixin \Fureev\Trees\QueryBuilder
 */
class PageUuid extends Page
{
    protected $keyType = 'uuid';

    protected static function buildTreeConfig(): Config
    {
        return new Config(['treeAttribute' => 'tree_id', 'parentAttributeType' => 'uuid']);
    }
}
