<?php

declare(strict_types=1);

namespace Fureev\Trees\Healthy;

use Fureev\Trees\QueryBuilderV2;
use Illuminate\Database\Query\Builder;

final readonly class MissingParentCheck extends AbstractCheck
{
    protected function query(): Builder
    {
        /** @var QueryBuilderV2 $builder */
        $builder = $this->model->newNestedSetQuery();

        return $builder
            ->toBase()
            ->whereNested(
                function (Builder $inner) use ($builder) {
                    $table   = $builder->wrappedTable();
                    $keyName = $builder->wrappedKey();

                    $grammar = $builder->getGrammar();

                    $parentIdName = $grammar->wrap((string)$this->model->parentAttribute());
                    $alias        = 'p';
                    $wrappedAlias = $grammar->wrapTable($alias);

                    $builder
                        ->toBase()
                        ->selectRaw('1')
                        ->from($this->model->getQuery()->raw("$table as $wrappedAlias"))
                        ->whereRaw("$table.$parentIdName = $wrappedAlias.$keyName")
                        ->limit(1);

                    $this->model->applyNestedSetScope($builder, $alias);


                    $inner
                        ->whereRaw("$parentIdName is not null")
                        ->addWhereExistsQuery($builder->getQuery(), 'and', true);
                }
            );
    }
}
