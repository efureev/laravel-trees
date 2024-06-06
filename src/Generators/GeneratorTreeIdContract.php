<?php

declare(strict_types=1);

namespace Fureev\Trees\Generators;

use Illuminate\Database\Eloquent\Model;

interface GeneratorTreeIdContract
{
    public function generateId(Model $model): string|int;
}
