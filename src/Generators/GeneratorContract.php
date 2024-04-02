<?php

declare(strict_types=1);

namespace Fureev\Trees\Generators;

use Illuminate\Database\Eloquent\Model;

interface GeneratorContract
{
    public function generate(Model $model): string|int;
}
