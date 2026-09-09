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

    /**
     * Takes the model to check, or the name of its class.
     *
     * A model handed over is kept as it is. It used to be reduced to its class name and built
     * again, which threw away everything the caller had set on it: a connection chosen with
     * `setConnection()`, and the attribute values that `getScopeAttributes()` narrows queries
     * by. The check then ran against the default connection, or against a scope of nulls.
     */
    public function __construct(Model|string $model)
    {
        $model = $model instanceof Model ? $model : Helper::instance($model);

        if (!Helper::isTreeNode($model)) {
            throw new Exception('Model should be a Tree Node');
        }

        $this->model = $model;
    }

    abstract protected function query(): Builder;

    /**
     * The number of nodes this check objects to. Zero means it found nothing.
     */
    public function check(): int
    {
        return $this->query()->count();
    }
}
