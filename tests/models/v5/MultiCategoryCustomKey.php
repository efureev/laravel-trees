<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\models\v5;

/**
 * @property string $title
 */
class MultiCategoryCustomKey extends AbstractMultiModel
{
    protected $fillable = ['title'];

    protected $table = 'categories_multi_custom_key';

    protected $primaryKey = 'custom_id';
}
