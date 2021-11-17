<?php

namespace Fureev\Trees\Tests\models;

use Fureev\Trees\QueryBuilder;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class SoftDeleteStructure
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
class SoftDeleteStructure extends Structure
{
    use SoftDeletes;
}
