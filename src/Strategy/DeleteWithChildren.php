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
        /** @var QueryBuilderV2 $query */
        $query = $model->newQuery();

        return $query
            ->descendantsQuery(null, true)
            ->when(
                $forceDelete,
                static fn($query) => $query->forceDelete(),
                static fn($query) => $query->delete(),
            );
    }
}
