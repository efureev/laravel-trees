<?php

declare(strict_types=1);

namespace Fureev\Trees\Strategy;

use Fureev\Trees\Contracts\TreeModel;
use Fureev\Trees\QueryBuilderV2;
use Illuminate\Database\Eloquent\Model;

class DeleteWithChildren implements DeleteStrategy
{
    /**
     * @param Model&TreeModel $model
     */
    public function handle(Model $model, bool $forceDelete): mixed
    {
        // A hard delete has to reach soft-deleted descendants too, or they would be
        // stranded inside the gap afterDelete() is about to close. Today that happens to
        // work because Builder::forceDelete() runs on the raw query and never applies the
        // global scopes; asking for the trashed rows explicitly keeps it working if that
        // implementation detail ever changes.
        /** @var QueryBuilderV2 $query */
        $query = $forceDelete ? $model->newNestedSetQuery() : $model->newQuery();

        return $query
            ->descendantsQuery(null, true)
            ->when(
                $forceDelete,
                static fn($query) => $query->forceDelete(),
                static fn($query) => $query->delete(),
            );
    }
}
