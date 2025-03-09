<?php

declare(strict_types=1);

namespace Fureev\Trees\Healthy;

use Fureev\Trees\QueryBuilderV2;
use Illuminate\Database\Query\Builder;

final readonly class WrongParentCheck extends AbstractCheck
{
    protected function query(): Builder
    {
        $table   = $this->model->wrappedTable();
        $keyName = $this->model->wrappedKey();

        $grammar = $this->model->getQuery()->getGrammar();

        $parentIdName = $grammar->wrap((string)$this->model->parentAttribute());

        $parentAlias = 'p';
        $childAlias  = 'c';
        $intermAlias = 'i';

        $waParent = $grammar->wrapTable($parentAlias);
        $waChild  = $grammar->wrapTable($childAlias);
        $waInterm = $grammar->wrapTable($intermAlias);

        $isMulti = $this->model->isMulti();

        /** @var QueryBuilderV2 $query */
        $query = $this->model->newNestedSetQuery($childAlias);

        $query
            ->toBase()
            ->from($this->model->getQuery()->raw("$table as $waChild, $table as $waParent, $table as $waInterm"))
            ->when(
                $isMulti,
                function (Builder $q) use ($waChild, $waParent, $waInterm) {
                    $tid = (string)$this->model->treeAttribute();
                    $q
                        ->whereRaw("$waChild.$tid = $waParent.$tid")
                        ->whereRaw("$waInterm.$tid = $waParent.$tid");
                }
            )
            ->whereRaw("$waChild.$parentIdName=$waParent.$keyName")
            ->whereRaw("$waInterm.$keyName <> $waParent.$keyName")
            ->whereRaw("$waInterm.$keyName <> $waChild.$keyName")
            ->whereNested(
                function (Builder $inner) use ($waInterm, $waChild, $waParent, $query) {
                    [
                        $lft,
                        $rgt,
                    ] = $query->wrappedColumns();

                    $inner
                        ->whereRaw("$waChild.$lft not between $waParent.$lft and $waParent.$rgt")
                        ->orWhereRaw("$waChild.$lft between $waInterm.$lft and $waInterm.$rgt")
                        ->whereRaw("$waInterm.$lft between $waParent.$lft and $waParent.$rgt");
                }
            );

        return $this->model->applyNestedSetScope(
            $this->model->applyNestedSetScope($query, $parentAlias),
            $intermAlias
        )->getQuery();
    }
}
