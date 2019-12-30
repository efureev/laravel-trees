<?php

namespace Fureev\Trees\Tests\models;

use Faker\Provider\Uuid;
use Fureev\Trees\Config;

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
    protected $keyType = 'uuid';

    protected $hidden = ['_setRoot'];

    protected $table = 'structure';

    protected $fillable = ['title', 'tree_id', 'params', 'path'];

    protected static function buildTreeConfig(): Config
    {
        return new Config([
            'treeAttribute' => 'tree_id',
            'parentAttributeType' => 'uuid',
            'treeAttributeType' => 'uuid',
            'autoGenerateTreeId' => false,
        ]);
    }

    /*public function generateTreeId()
    {
        return Uuid::uuid();
    }*/

}
