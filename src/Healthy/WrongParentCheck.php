<?php

declare(strict_types=1);

namespace Fureev\Trees\Healthy;

use Fureev\Trees\QueryBuilderV2;
use Illuminate\Database\Query\Builder;

/**
 * Counts the nodes whose `parent_id` disagrees with where they actually sit.
 *
 * One join, on the primary key. The previous version joined three copies of the table and
 * filtered them with inequalities, which no index helps: twenty seconds at twenty thousand rows,
 * and quadratic from there. It also counted (child, parent, intermediate) triples rather than
 * nodes, so the same single defect scored higher on a bigger tree.
 *
 * Two things make a parent wrong. It may not enclose the child at all, which is what the old
 * check looked for. Or it may enclose it from too far away — something sits between them — and
 * that is a level apart rather than a third row to find: an immediate parent is exactly one
 * level up. Reading it off the level also catches a corrupted `lvl`, which the old check could
 * not see, and needs no third node to exist, which the old one did.
 */
final readonly class WrongParentCheck extends AbstractCheck
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

        [
            $lft,
            $rgt,
            $lvl,
        ] = $query->wrappedColumns();

        $isMulti = $this->model->isMulti();

        $query
            ->toBase()
            ->from($this->model->getQuery()->raw("$table as $waChild, $table as $waParent"))
            ->whereRaw("$waChild.$parent = $waParent.$keyName")
            ->whereNested(
                function (Builder $inner) use ($waChild, $waParent, $lft, $rgt, $lvl, $isMulti, $grammar) {
                    $inner
                        ->whereRaw("not ($waParent.$lft < $waChild.$lft and $waChild.$rgt < $waParent.$rgt)")
                        ->orWhereRaw("$waChild.$lvl <> $waParent.$lvl + 1");

                    if ($isMulti) {
                        $tree = $grammar->wrap((string)$this->model->treeAttribute());
                        $inner->orWhereRaw("$waChild.$tree <> $waParent.$tree");
                    }
                }
            );

        return $this->model->applyNestedSetScope($query, $parentAlias)->getQuery();
    }
}
