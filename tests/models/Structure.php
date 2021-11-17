<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\models;

use Fureev\Trees\Config\Base;
use Fureev\Trees\Config\TreeAttribute;
use Fureev\Trees\QueryBuilder;

/**
 * Class Structure
 *
 * @package Fureev\Trees\Tests\models
 * @property string $id
 * @property string $parent_id
 * @property string $title
 * @property string $tree_id
 * @property array $path
 * @property array $params
 *
 * @mixin QueryBuilder
 */
class Structure extends Page
{
    protected $keyType = 'string';

    protected $table = 'structures';

    protected $fillable = ['title', 'tree_id', 'params', 'path'];

    protected $casts = [
        'path'   => 'array',
        'params' => 'array',
        'title'  => 'string',
    ];

    protected static function buildTreeConfig(): Base
    {
        return new Base((new TreeAttribute('uuid'))->setName('tree_id'));
    }
}
