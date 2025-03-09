<?php

declare(strict_types=1);

namespace Fureev\Trees\Healthy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

abstract readonly class AbstractCheck
{
    protected Model $model;

    public function __construct(Model|string $model)
    {
        if ($model instanceof Model) {
            $model = $model::class;
        }

        $this->model = instance($model);
    }

    abstract protected function query(): Builder;

    public function check(): int
    {
        return $this->query()->count();
    }
}
