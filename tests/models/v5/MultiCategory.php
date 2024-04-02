<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\models\v5;

/**
 * @property string $title
 */
class MultiCategory extends AbstractMultiModel
{
    protected $fillable = ['title'];

    protected $table = 'categories_multi';
}
