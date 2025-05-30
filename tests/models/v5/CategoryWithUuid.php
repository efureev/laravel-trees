<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\models\v5;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * @property string $id
 * @property string $title
 */
class CategoryWithUuid extends AbstractModel
{
    use HasUuids;

    protected $fillable = ['title'];

    protected $table = 'categories';
}
