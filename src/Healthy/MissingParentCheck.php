<?php

declare(strict_types=1);

namespace Fureev\Trees\Healthy;

use Fureev\Trees\QueryBuilderV2;
use Illuminate\Database\Query\Builder;

/**
 * Counts the nodes whose `parent_id` names a row that is not there.
 *
 * This is what a query-level delete leaves behind: the row goes, the children keep pointing at
 * it, and nothing else about the tree looks wrong. The bounds still nest, so no other check
 * notices.
 *
 * A left join and a null test. The previous version asked the same question with a correlated
 * `not exists` per row, which had to build a second builder to avoid the query referencing
 * itself.
 */
final readonly class MissingParentCheck extends AbstractCheck
{
    protected function query(): Builder
    {
        $grammar = $this->model->getQuery()->getGrammar();

        $table   = $this->model->wrappedTable();
        $keyName = $this->model->wrappedKey();
        $parent  = $grammar->wrap((string)$this->model->parentAttribute());

        $childAlias  = 'c';
        $parentAlias = 'p';

        $waChild  = $grammar->wrapTable($childAlias);
        $waParent = $grammar->wrapTable($parentAlias);

        /** @var QueryBuilderV2 $query */
        $query = $this->model->newNestedSetQuery($childAlias);

        $query
            ->toBase()
            ->from($this->model->getQuery()->raw("$table as $waChild"))
            ->leftJoin(
                $this->model->getQuery()->raw("$table as $waParent"),
                fn($join) => $join->whereRaw("$waParent.$keyName = $waChild.$parent")
            )
            ->whereRaw("$waChild.$parent is not null")
            ->whereRaw("$waParent.$keyName is null");

        return $query->getQuery();
    }
}
