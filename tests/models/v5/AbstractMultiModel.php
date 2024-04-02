<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\models\v5;

use Fureev\Trees\Config\Builder;
use Fureev\Trees\UseTree;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $lft
 * @property int $rgt
 * @property int $lvl
 * @property ?int $parent
 * @property int $tree_id
 * @property int|string $id
 * @property array $path
 * @property array $params
 */
abstract class AbstractMultiModel extends Model
{
    use UseTree;

    protected $casts = [
        'path'   => 'array',
        'params' => 'array',
    ];

    public $timestamps = false;

    protected static function buildTree(): Builder
    {
        return Builder::defaultMulti();
    }

}
