<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\models\v5;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * @property string $id
 * @property string $title
 */
class CategoryWithUlid extends AbstractModel
{
    use HasUlids;

    protected $fillable = ['title'];

    protected $table = 'categories';
}
