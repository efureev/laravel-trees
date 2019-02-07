<?php

namespace Fureev\Trees\Tests\models;


/**
 * Class Category
 *
 * @package Fureev\Trees\Tests\models
 *
 * @property int $id
 * @property string $name
 * @property int $lvl
 * @mixin \Fureev\Trees\QueryBuilder
 */
class CategorySoftDelete extends Category
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

}
