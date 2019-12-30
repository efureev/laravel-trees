<?php

namespace Fureev\Trees\Tests\models;

use Fureev\Trees\NestedSetTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * Class BaseModel
 *
 * wrapper for tests
 *
 * @package Fureev\Trees\Tests\models
 * @property int $lvl
 * @property array $path
 *
 * @mixin \Fureev\Trees\QueryBuilder
 */
abstract class BaseModel extends Model
{
    use NestedSetTrait;

    protected $casts = [
        'path' => 'array',
        'params' => 'array',
    ];

    public $timestamps = false;

}
