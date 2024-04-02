<?php

declare(strict_types=1);

namespace Fureev\Trees\Strategy;

use Fureev\Trees\UseTree;
use Illuminate\Database\Eloquent\Model;

class DeleteWithChildren implements DeleteStrategy
{
    /**
     * @param Model|UseTree $model
     */
    public function handle(Model $model, bool $forceDelete): mixed
    {
        return $model->newQuery()
            ->descendantsQuery(null, true)
            ->when(
                $forceDelete,
                static fn($query) => $query->forceDelete(),
                static fn($query) => $query->delete(),
            );
    }
}
