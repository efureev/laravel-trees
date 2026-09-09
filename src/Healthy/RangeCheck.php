<?php

declare(strict_types=1);

namespace Fureev\Trees\Healthy;

use Fureev\Trees\QueryBuilderV2;
use Illuminate\Database\Query\Builder;

/**
 * Counts the trees whose numbering has holes in it.
 *
 * A tree of N nodes uses the numbers 1..2N across its two bound columns, each once. So the
 * largest right bound has to be twice the number of nodes. Anything else means bounds were
 * vacated and never reclaimed, or rows went missing.
 *
 * Nothing else sees this. The bounds still nest, nothing is duplicated, and every parent still
 * encloses its children — the tree is merely wider than its contents. That is what a subtree
 * deleted by query leaves behind, and what `removeDescendants()` leaves on purpose.
 *
 * Counted per tree rather than per node: a hole belongs to the tree, and there is no one node
 * to blame for it.
 */
final readonly class RangeCheck extends AbstractCheck
{
    protected function query(): Builder
    {
        $grammar = $this->model->getQuery()->getGrammar();

        $right   = (string)$this->model->rightAttribute();
        $wrapped = $grammar->wrap($right);

        $isMulti = $this->model->isMulti();
        $tree    = $isMulti ? (string)$this->model->treeAttribute() : null;

        /** @var QueryBuilderV2 $builder */
        $builder = $this->model->newNestedSetQuery();

        $perTree = $builder
            ->toBase()
            ->selectRaw("max($wrapped) as bound, count(*) as nodes");

        if ($tree !== null) {
            $perTree->addSelect($tree)->groupBy($tree);
        }

        return $this->model->newNestedSetQuery()
            ->toBase()
            ->newQuery()
            ->fromSub($perTree, 't')
            ->whereRaw('t.bound <> t.nodes * 2');
    }
}
