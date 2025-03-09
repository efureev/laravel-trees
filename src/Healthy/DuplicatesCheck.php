<?php

declare(strict_types=1);

namespace Fureev\Trees\Healthy;

use Fureev\Trees\QueryBuilderV2;
use Illuminate\Database\Query\Builder;

final readonly class DuplicatesCheck extends AbstractCheck
{
    protected function query(): Builder
    {
        $table   = $this->model->wrappedTable();
        $keyName = $this->model->wrappedKey();

        $firstAlias  = 'c1';
        $secondAlias = 'c2';

        $waFirst  = $this->model->getQuery()->getGrammar()->wrapTable($firstAlias);
        $waSecond = $this->model->getQuery()->getGrammar()->wrapTable($secondAlias);

        $isMulti = $this->model->isMulti();

        /** @var QueryBuilderV2 $query */
        $query = $this->model->newNestedSetQuery($firstAlias);

        $query
            ->toBase()
            ->from($this->model->getQuery()->raw("$table as $waFirst, $table as $waSecond"))
            ->whereRaw("$waFirst.$keyName <> $waSecond.$keyName")
            ->when(
                $isMulti,
                function (Builder $q) use ($waFirst, $waSecond) {
                    $tid = (string)$this->model->treeAttribute();
                    $q->whereRaw("$waFirst.$tid = $waSecond.$tid");
                }
            )
            ->whereNested(
                function (Builder $inner) use ($waFirst, $waSecond, $query) {
                    [
                        $lft,
                        $rgt,
                    ] = $query->wrappedColumns();

                    $inner
                        ->orWhereRaw("$waFirst.$lft=$waSecond.$lft")
                        ->orWhereRaw("$waFirst.$rgt=$waSecond.$rgt")
                        ->orWhereRaw("$waFirst.$lft=$waSecond.$rgt")
                        ->orWhereRaw("$waFirst.$rgt=$waSecond.$lft");
                }
            );

        return $this->model->applyNestedSetScope($query, $secondAlias)->getQuery();
    }
}
