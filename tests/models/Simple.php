<?php

namespace Fureev\Trees\Tests\models;

/**
 * Class Simple
 *
 * @package Fureev\Trees\Tests\models
 * @property int $id
 * @property string $title
 *
 * @mixin \Fureev\Trees\QueryBuilder
 */
class Simple extends BaseModel
{
    protected $fillable = ['title'];

    protected $table = 'categories';
}
