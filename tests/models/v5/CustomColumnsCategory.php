<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\models\v5;

use Fureev\Trees\Config\Attribute;
use Fureev\Trees\Config\AttributeType;
use Fureev\Trees\Config\Builder;

/**
 * @property string $title
 */
class CustomColumnsCategory extends AbstractModel
{
    protected $fillable = ['title'];

    protected $table = 'categories';

    protected static function buildTree(): Builder
    {
        return Builder::default()->setAttributes(
            Attribute::make(AttributeType::Left)->setColumnName('left_bound'),
            Attribute::make(AttributeType::Right)->setColumnName('right_bound'),
            Attribute::make(AttributeType::Level)->setColumnName('depth'),
            Attribute::make(AttributeType::Parent)->setColumnName('pid')->setNullable(),
        );
    }
}
