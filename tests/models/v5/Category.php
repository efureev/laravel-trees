<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\models\v5;

/**
 * @property string $title
 */
class Category extends AbstractModel
{
    protected $fillable = ['title'];

    protected $table = 'categories';
}
