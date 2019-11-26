<?php

namespace Fureev\Trees\Tests\models;

use Fureev\Trees\Config;

/**
 * Class Page
 *
 * @package Fureev\Trees\Tests\models
 * @property int $id
 * @property string $title
 * @property int $tree_id
 *
 * @mixin \Fureev\Trees\QueryBuilder
 */
class Page extends BaseModel
{
    protected $fillable = ['title', '_setRoot'];

    protected $hidden = ['_setRoot', 'lft', 'rgt', 'lvl', 'tree_id', 'parent'];

    protected $table = 'pages';

    protected static function buildTreeConfig(): Config
    {
        return new Config(['treeAttribute' => 'tree_id']);
    }
}
