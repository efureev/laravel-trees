<?php

declare(strict_types=1);

namespace Fureev\Trees\Healthy;

use Fureev\Trees\QueryBuilderV2;
use Illuminate\Database\Query\Builder;

/**
 * Counts the trees that do not have exactly one root.
 *
 * A single-tree model holds one root and the package refuses a second at write time; a
 * multi-tree model holds one per tree. Rows written around the package can break either, and
 * two roots whose bounds do not happen to collide slip past every other check.
 *
 * A root is a node with no parent. On a multi-tree model the roots are counted per tree, and a
 * tree with none at all cannot be seen here — it has no rows to group.
 */
final readonly class RootCheck extends AbstractCheck
{
    protected function query(): Builder
    {
        $grammar = $this->model->getQuery()->getGrammar();

        $parent = $grammar->wrap((string)$this->model->parentAttribute());
        $tree   = $this->model->isMulti() ? (string)$this->model->treeAttribute() : null;

        /** @var QueryBuilderV2 $builder */
        $builder = $this->model->newNestedSetQuery();

        $roots = $builder
            ->toBase()
            ->selectRaw('count(*) as roots')
            ->whereRaw("$parent is null");

        if ($tree !== null) {
            $roots->addSelect($tree)->groupBy($tree);
        }

        return $this->model->newNestedSetQuery()
            ->toBase()
            ->newQuery()
            ->fromSub($roots, 'r')
            ->whereRaw('r.roots <> 1');
    }
}
