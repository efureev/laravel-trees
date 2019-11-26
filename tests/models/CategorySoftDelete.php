<?php

namespace Fureev\Trees\Tests\models;


use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Category
 *
 * @package Fureev\Trees\Tests\models
 *
 * @mixin \Fureev\Trees\QueryBuilder
 */
class CategorySoftDelete extends Category
{
    use SoftDeletes;

}
