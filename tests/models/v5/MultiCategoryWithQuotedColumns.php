<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\models\v5;

use Fureev\Trees\Config\Attribute;
use Fureev\Trees\Config\AttributeType;
use Fureev\Trees\Config\Builder;

/**
 * Column names that PostgreSQL folds to lower case unless they are quoted, so any raw SQL
 * that interpolates them without going through the grammar breaks on this model.
 *
 * @property string $title
 */
class MultiCategoryWithQuotedColumns extends AbstractModel
{
    protected $fillable = ['title'];

    protected $table = 'quoted_columns';

    protected static function buildTree(): Builder
    {
        return Builder::defaultMulti()->setAttributes(
            Attribute::make(AttributeType::Left)->setColumnName('leftBound'),
            Attribute::make(AttributeType::Right)->setColumnName('rightBound'),
            Attribute::make(AttributeType::Level)->setColumnName('treeDepth'),
            Attribute::make(AttributeType::Parent)->setColumnName('parentId')->setNullable(),
            Attribute::make(AttributeType::Tree)->setColumnName('treeId'),
        );
    }
}
