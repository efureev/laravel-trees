<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\models\v5;

/**
 * @property string $title
 */
class CategoryCustomKey extends AbstractModel
{
    protected $fillable = ['title'];

    protected $table = 'categories_custom_key';

    protected $primaryKey = 'custom_id';
}
