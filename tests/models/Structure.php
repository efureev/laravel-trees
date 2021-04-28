<?php

namespace Fureev\Trees\Tests\models;

use Fureev\Trees\Config;
use Fureev\Trees\Config\Base;

/**
 * Class Structure
 *
 * @package Fureev\Trees\Tests\models
 * @property string $id
 * @property string $parent_id
 *
 * @mixin \Fureev\Trees\QueryBuilder
 */
class Structure extends Page
{
    protected $keyType = 'string';

    protected $hidden = ['_setRoot'];

    protected $table = 'structure';

    protected $fillable = ['title', 'tree_id', 'params', 'path'];

    protected static function buildTreeConfig(): Base
    {
        $config = new Base();
        $config->setAttribute('tree', (new Config\TreeAttribute())->setType('uuid')->setAutoGenerate(false));

        return $config;
    }
}
