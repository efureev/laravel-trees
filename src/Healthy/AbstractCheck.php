<?php

declare(strict_types=1);

namespace Fureev\Trees\Healthy;

use Fureev\Trees\Config\Helper;
use Fureev\Trees\Contracts\TreeModel;
use Fureev\Trees\Exceptions\Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

abstract readonly class AbstractCheck
{
    /** @var Model&TreeModel */
    protected Model $model;

    public function __construct(Model|string $model)
    {
        if ($model instanceof Model) {
            $model = $model::class;
        }

        $this->model = instance($model);

        if (!Helper::isTreeNode($this->model)) {
            throw new Exception('Model should be a Tree Node');
        }
    }

    abstract protected function query(): Builder;

    public function check(): int
    {
        return $this->query()->count();
    }
}
