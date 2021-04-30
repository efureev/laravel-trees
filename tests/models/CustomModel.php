<?php

namespace Fureev\Trees\Tests\models;

use Fureev\Trees\Config\Base;
use Fureev\Trees\Config\LeftAttribute;
use Fureev\Trees\Config\LevelAttribute;
use Fureev\Trees\Config\ParentAttribute;
use Fureev\Trees\Config\RightAttribute;
use Fureev\Trees\Config\TreeAttribute;
use Ramsey\Uuid\Uuid;

/**
 * Class Page
 *
 * @package Fureev\Trees\Tests\models
 * @property string $id
 * @property string $parent_id
 *
 * @mixin \Fureev\Trees\QueryBuilder
 */
class CustomModel extends BaseModel
{
    public const TREE_ID   = 'struct_id';
    public const PARENT_ID = 'papa_id';

    protected $keyType = 'string';

    protected $primaryKey = 'num';

    protected $table = 'pages_uuid';

    protected $fillable = ['title', '_setRoot'];

    protected $hidden = ['_setRoot', 'lft', 'rgt', 'lvl', 'struct_id', 'parent_id'];

    protected static function buildTreeConfig(): Base
    {
        return Base::make()
            ->setAttributeTree(TreeAttribute::make('uuid')->setName(self::TREE_ID))
            ->setAttribute('parent', ParentAttribute::make()->setName(self::PARENT_ID))
            ->setAttribute('left', LeftAttribute::make()->setName('left_offset'))
            ->setAttribute('right', RightAttribute::make()->setName('right_offset'))
            ->setAttribute('level', LevelAttribute::make()->setName('deeeeep'));
    }

    public function generateTreeId(): string
    {
        return Uuid::uuid4()->toString();
    }
}
