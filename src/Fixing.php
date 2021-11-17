<?php

declare(strict_types=1);

namespace Fureev\Trees;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as Query;
use Illuminate\Database\Query\Expression;

/**
 * @mixin QueryBuilder
 */
trait Fixing
{

    public function fixMultiTree(): array
    {
        if (!$this->model->isMultiTree()) {
            return (array)$this->fixTree();
        }

        $rootsByTree = $this->model->newNestedSetQuery()->root()->get()->groupBy('tree_id');

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
     * @param Model|NestedSetTrait|null $root
     *
     * @return int The number of changed nodes
     */
    public function fixTree(?Model $root = null): int
    {
        $columns   = $this->model->getTreeConfig()->columns();
        $columns[] = $this->model->getKeyName();

        $dictionary = $this->model
            ->newNestedSetQuery()
            ->when($root, function (self $query) use ($root) {
                return $query->whereDescendantOf($root);
            })
            ->defaultOrder()
            ->get($columns)
            ->groupBy($this->model->parentAttribute()->name())
            ->all();


        return $this->fixNodes($dictionary, $root);
    }

    /**
     * @param array $dictionary
     * @param null|Model|NestedSetTrait $parent
     *
     * @return int|void
     */
    protected function fixNodes(array &$dictionary, ?Model $parent = null)
    {
        $parentId    = $parent ? $parent->getKey() : null;
        $parentLevel = $parent ? $parent->levelValue() : 0;
        $cut         = $parent ? abs($parent->leftOffset()) + 1 : 1;

        $updated = [];
        $moved   = 0;

        $cut = self::reorderNodes($dictionary, $updated, $parentId, $cut, $parentLevel);

        // Save nodes that have invalid parent as roots
        while (!empty($dictionary)) {
            $dictionary[null] = reset($dictionary);

            unset($dictionary[key($dictionary)]);

            $cut = self::reorderNodes($dictionary, $updated, $parentId, $cut, $parentLevel);
        }

        if ($parent && ($grown = $cut - abs($parent->rightOffset())) !== 0) {
            $moved = $parent->newScopedQuery()->makeGap(abs($parent->rightOffset()) + 1, $grown);

            $updated[] = $parent->setAttribute($parent->rightAttribute()->name(), $cut);
        }

        foreach ($updated as $model) {
            $model->saveQuietly();
        }

        return count($updated) + $moved;
    }

    protected static function reorderNodes(array &$dictionary, array &$updated, $parentId, $cut, $parentLevel = 0)
    {
        if (!isset($dictionary[$parentId])) {
            return $cut;
        }
        $level = $parentId ? $parentLevel + 1 : 0;

        /** @var Model|NestedSetTrait $model */
        foreach ($dictionary[$parentId] as $model) {
            $lft = $cut;

            $cut = self::reorderNodes($dictionary, $updated, $model->getKey(), $cut + 1, $level);

            $model
                ->setAttribute($model->leftAttribute()->name(), $lft)
                ->setAttribute($model->rightAttribute()->name(), $cut)
                ->setAttribute($model->parentAttribute()->name(), $parentId)
                ->setAttribute($model->levelAttribute()->name(), $level);

            if ($model->isDirty()) {
                $updated[] = $model;
            }

            ++$cut;
        }

        unset($dictionary[$parentId]);

        return $cut;
    }

    public function makeGap($cut, $height)
    {
        $params = compact('cut', 'height');

        $where = [];

        if ($this->model->isMultiTree() && ($val = $this->model->treeValue()) !== null) {
            $where = [$this->model->treeAttribute()->name() => $val];
        }

        $query = $this->toBase()
            ->whereNested(function (Query $inner) use ($cut) {
                $inner->where($this->model->leftAttribute()->name(), '>=', $cut);
                $inner->orWhere($this->model->rightAttribute()->name(), '>=', $cut);
            })
            ->when($where, function (Query $q) use ($where) {
                $q->where($where);
            });

        return $query->update($this->patch($params));
    }

    protected function patch(array $params)
    {
        $grammar = $this->query->getGrammar();

        $columns = [];

        foreach ([$this->model->leftAttribute()->name(), $this->model->rightAttribute()->name()] as $col) {
            $columns[$col] = $this->columnPatch($grammar->wrap($col), $params);
        }

        return $columns;
    }

    protected function columnPatch($col, array $params)
    {
        extract($params);

        /** @var int $height */
        if ($height > 0) {
            $height = '+' . $height;
        }

        if (isset($cut)) {
            return new Expression("case when {$col} >= {$cut} then {$col}{$height} else {$col} end");
        }

        /** @var int $distance */
        /** @var int $lft */
        /** @var int $rgt */
        /** @var int $from */
        /** @var int $to */
        if ($distance > 0) {
            $distance = '+' . $distance;
        }

        return new Expression(
            "case " .
            "when {$col} between {$lft} and {$rgt} then {$col}{$distance} " . // Move the node
            "when {$col} between {$from} and {$to} then {$col}{$height} " . // Move other nodes
            "else {$col} end"
        );
    }


    public function fixSubTree(Model $root)
    {
        return $this->fixTree($root);
    }
}
