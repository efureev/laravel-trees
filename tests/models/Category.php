<?php

namespace Fureev\Trees\Tests\models;

/**
 * Class Category
 *
 * @package Fureev\Trees\Tests\models
 * @property int $id
 * @property string $title
 *
 * @mixin \Fureev\Trees\QueryBuilder
 */
class Category extends BaseModel
{
    protected $fillable = ['title', '_setRoot'];

    protected $table = 'categories';
}
