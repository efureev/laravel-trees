<?php

namespace Fureev\Trees\Tests\models;

use Fureev\Trees\Config;
use Fureev\Trees\NestedSetTrait;
use Illuminate\Database\Eloquent\Model;

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
class Page extends Model //implements Treeable
{
    use NestedSetTrait;

    protected $fillable = ['title', '_setRoot'];

    public $timestamps = false;

    protected $table = 'pages';

    protected static function buildTreeConfig(): Config
    {
        return new Config(['treeAttribute' => 'tree_id']);
    }

    /* public static function resetActionsPerformed()
     {
         static::$actionsPerformed = 0;
     }*/
}
