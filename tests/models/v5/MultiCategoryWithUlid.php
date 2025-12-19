<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\models\v5;

use Fureev\Trees\Config\Attribute;
use Fureev\Trees\Config\AttributeType;
use Fureev\Trees\Config\Builder;
use Fureev\Trees\Config\FieldType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * @property string $id
 * @property string $title
 * @property string $tree_id
 */
class MultiCategoryWithUlid extends AbstractModel
{
    use HasUlids;

    protected $fillable = ['title'];

    protected $table = 'multi_categories';

    protected static function buildTree(): Builder
    {
        return Builder::defaultMulti()->setAttributes(
            Attribute::make(AttributeType::Tree, FieldType::ULID)
        );
    }
}
