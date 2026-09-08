<?php

declare(strict_types=1);

namespace Fureev\Trees\QueryBuilder;

use Fureev\Trees\QueryBuilderV2;
use Fureev\Trees\UseTree;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as Query;
use Illuminate\Database\Query\Expression;

/**
 * @mixin QueryBuilderV2<Model>
 *
 * !! Be careful !! It's not verified and tested on new Version 5!
 */
trait Fixing
{
    public function fixMultiTree(): array
    {
        if (!$this->model->isMulti()) {
            return (array)$this->fixTree();
        }

        $rootsByTree = $this->model->newNestedSetQuery()->root()->get()->groupBy((string)$this->model->treeAttribute());

        $list = [];
        foreach ($rootsByTree as $treeId => $roots) {
            foreach ($roots as $root) {
                $list[$treeId] = $this->fixTree($root);
            }
        }

        return $list;
    }

    /**
     * Fixes the tree based on parentage info.
     *
     * Nodes with invalid parent are saved as roots.
     *
     * @param Model|UseTree|null $root
     *
     * @return int The number of changed nodes
     */
    public function fixTree(?Model $root = null): int
    {
        $columns   = $this->model->getTreeConfig()->columnsNames();
        $columns[] = $this->model->getKeyName();

        $dictionary = $this->model
            ->newNestedSetQuery()
            ->when(
                $root,
                function (self $query) use ($root) {
                    $query->whereDescendantOf($root);

                    // For multi-trees, descendants are matched by bounds only, which
                    // overlap across trees. Restrict the query to the root's tree.
                    if ($root->isMulti() && ($treeId = $root->treeValue()) !== null) {
                        $query->where((string)$root->treeAttribute(), $treeId);
                    }

                    return $query;
                }
            )
            ->defaultOrder()
            ->get($columns)
            ->groupBy((string)$this->model->parentAttribute())
            ->all();


        return $this->fixNodes($dictionary, $root);
    }

    /**
     * @param array $dictionary
     * @param null|Model|UseTree $parent
     */
    protected function fixNodes(array &$dictionary, ?Model $parent = null): int
    {
        $parentId    = $parent?->getKey();
        $parentLevel = $parent ? $parent->levelValue() : 0;
        $cut         = $parent ? (abs($parent->leftValue()) + 1) : 1;

        $updated = [];
        $moved   = 0;

        $cut = self::reorderNodes($dictionary, $updated, $parentId, $cut, $parentLevel);

        // Save nodes that have invalid parent as roots
        while (!empty($dictionary)) {
            $dictionary[null] = reset($dictionary);

            unset($dictionary[key($dictionary)]);

            $cut = self::reorderNodes($dictionary, $updated, $parentId, $cut, $parentLevel);
        }

        if ($parent && ($grown = $cut - abs($parent->rightValue())) !== 0) {
            $moved = $parent->newScopedQuery()->makeGap((abs($parent->rightValue()) + 1), $grown);

            $updated[] = $parent->setAttribute((string)$parent->rightAttribute(), $cut);
        }

        foreach ($updated as $model) {
            $model->saveQuietly();
        }

        return (count($updated) + $moved);
    }

    protected static function reorderNodes(
        array &$dictionary,
        array &$updated,
        int|string|null $parentId,
        int $cut,
        int $parentLevel = 0
    ) {
        if (!isset($dictionary[$parentId])) {
            return $cut;
        }

        $level = $parentId ? ($parentLevel + 1) : 0;

        /** @var Model|UseTree $model */
        foreach ($dictionary[$parentId] as $model) {
            $lft = $cut;

            $cut = static::reorderNodes($dictionary, $updated, $model->getKey(), ($cut + 1), $level);

            $model
                ->setAttribute((string)$model->leftAttribute(), $lft)
                ->setAttribute((string)$model->rightAttribute(), $cut)
                ->setAttribute((string)$model->parentAttribute(), $parentId)
                ->setAttribute((string)$model->levelAttribute(), $level);

            if ($model->isDirty()) {
                $updated[] = $model;
            }

            ++$cut;
        }

        unset($dictionary[$parentId]);

        return $cut;
    }

    /**
     * Shift every bound at or past $cut by $height.
     *
     * @return int The number of updated rows
     */
    public function makeGap(int $cut, int $height): int
    {
        $params = compact('cut', 'height');

        $where = [];

        if ($this->model->isMulti() && ($val = $this->model->treeValue()) !== null) {
            $where = [(string)$this->model->treeAttribute() => $val];
        }

        $query = $this->toBase()
            ->whereNested(
                function (Query $inner) use ($cut) {
                    $inner->where((string)$this->model->leftAttribute(), '>=', $cut);
                    $inner->orWhere((string)$this->model->rightAttribute(), '>=', $cut);
                }
            )
            ->when(
                $where,
                function (Query $q) use ($where) {
                    $q->where($where);
                }
            );

        return $query->update($this->patch($params));
    }

    protected function patch(array $params): array
    {
        $grammar = $this->query->getGrammar();
        $columns = [];

        foreach ([(string)$this->model->leftAttribute(), (string)$this->model->rightAttribute()] as $col) {
            $columns[$col] = $this->columnPatch($grammar->wrap($col), $params);
        }

        return $columns;
    }

    /**
     * @param array{cut: int, height: int} $params
     */
    protected function columnPatch(mixed $col, array $params): Expression
    {
        $cut = (int)$params['cut'];

        // The sign has to be explicit: an unsigned zero would render as `"lft"0`,
        // which is a syntax error rather than the no-op it is meant to be.
        $height = sprintf('%+d', (int)$params['height']);

        return new Expression("case when $col >= $cut then $col$height else $col end");
    }


    public function fixSubTree(Model $root): int
    {
        return $this->fixTree($root);
    }
}
