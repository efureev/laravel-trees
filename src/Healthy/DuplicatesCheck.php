<?php

declare(strict_types=1);

namespace Fureev\Trees\Healthy;

use Fureev\Trees\QueryBuilderV2;
use Illuminate\Database\Query\Builder;

/**
 * Counts the nodes sharing a bound with another node.
 *
 * Within one tree the left and right bounds together form the numbers 1..2N, each used once, so
 * a repeat is a defect. Gathering both columns into one list and grouping it finds every repeat
 * in a single pass.
 *
 * The previous version cross joined the table with itself and compared the four combinations of
 * bounds with inequalities, which no index helps: thirteen seconds at twenty thousand rows, and
 * quadratic from there. It also counted ordered pairs rather than nodes, so two nodes sharing a
 * bound scored two and three sharing one scored six.
 */
final readonly class DuplicatesCheck extends AbstractCheck
{
    protected function query(): Builder
    {
        $keyName = $this->model->getKeyName();
        $left    = (string)$this->model->leftAttribute();
        $right   = (string)$this->model->rightAttribute();

        $isMulti = $this->model->isMulti();
        $tree    = $isMulti ? (string)$this->model->treeAttribute() : null;

        // Every bound of every node as one row: the node it belongs to, the value, and the tree
        // it lives in. Both halves come from newNestedSetQuery(), so a model that narrows its
        // queries through getScopeAttributes() narrows this one too.
        $bounds = $this->boundsOf($left, $keyName, $tree)
            ->unionAll($this->boundsOf($right, $keyName, $tree));

        $repeated = $this->model->newNestedSetQuery()->toBase()->newQuery()
            ->fromSub($bounds, 'b')
            ->select('b.bound')
            ->groupBy('b.bound')
            ->havingRaw('count(*) > 1');

        if ($isMulti) {
            $repeated->addSelect('b.tree')->groupBy('b.tree');
        }

        $offending = $this->model->newNestedSetQuery()->toBase()->newQuery()
            ->fromSub(
                $this->boundsOf($left, $keyName, $tree)
                    ->unionAll($this->boundsOf($right, $keyName, $tree)),
                'u'
            )
            ->joinSub(
                $repeated,
                'd',
                function ($join) use ($isMulti) {
                    $join->on('d.bound', '=', 'u.bound');

                    if ($isMulti) {
                        $join->on('d.tree', '=', 'u.tree');
                    }
                }
            )
            ->distinct()
            ->select('u.node');

        return $this->model->newNestedSetQuery()->toBase()->newQuery()->fromSub($offending, 'o');
    }

    /**
     * One bound column, listed as (node, bound[, tree]).
     */
    private function boundsOf(string $column, string $keyName, ?string $tree): Builder
    {
        /** @var QueryBuilderV2 $builder */
        $builder = $this->model->newNestedSetQuery();

        $columns = [
            "$keyName as node",
            "$column as bound",
        ];

        if ($tree !== null) {
            $columns[] = "$tree as tree";
        }

        return $builder->toBase()->select($columns);
    }
}
