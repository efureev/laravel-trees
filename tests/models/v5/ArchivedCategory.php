<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\models\v5;

use Illuminate\Database\Eloquent\SoftDeletes;

class ArchivedCategory extends Category
{
    use SoftDeletes;
}
