<?php

namespace Fureev\Trees;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as Query;
use Illuminate\Database\Query\Expression;

/**
 * @mixin QueryBuilder
 */
trait Fixing
{
    /**
     * Fixes the tree based on parentage info.
     *
     * Nodes with invalid parent are saved as roots.
     *
     * @param Model|NestedSetTrait|null $root
     *
     * @return int The number of changed nodes
     */
    public function fixTree(?Model $root = null)
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
        $parentId = $parent ? $parent->getKey() : null;
        $cut      = $parent ? $parent->leftOffset() + 1 : 1;

        $updated = [];
        $moved   = 0;

        $cut = self::reorderNodes($dictionary, $updated, $parentId, $cut);

        // Save nodes that have invalid parent as roots
        while (!empty($dictionary)) {
            $dictionary[null] = reset($dictionary);

            unset($dictionary[key($dictionary)]);

            $cut = self::reorderNodes($dictionary, $updated, $parentId, $cut);
        }


        if ($parent && ($grown = $cut - $parent->parentValue()) !== 0) {
            $moved = $this->model->newScopedQuery()->makeGap($parent->rightOffset() + 1, $grown);

            $updated[] = $parent->setAttribute($parent->rightAttribute()->name(), $cut);
        }

        foreach ($updated as $model) {
            $model->save();
        }

        return count($updated) + $moved;
    }

    public function makeGap($cut, $height)
    {
        $params = compact('cut', 'height');

        $query = $this->toBase()->whereNested(function (Query $inner) use ($cut) {
            $inner->where($this->model->leftAttribute()->name(), '>=', $cut);
            $inner->orWhere($this->model->rightAttribute()->name(), '>=', $cut);
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

    protected static function reorderNodes(array &$dictionary, array &$updated, $parentId = null, $cut = 1)
    {
        if (!isset($dictionary[$parentId])) {
            return $cut;
        }

        /** @var Model|NestedSetTrait $model */
        foreach ($dictionary[$parentId] as $model) {
            $lft = $cut;

            $cut = self::reorderNodes($dictionary, $updated, $model->getKey(), $cut + 1);

            $model
                ->setAttribute($model->leftAttribute()->name(), $lft)
                ->setAttribute($model->rightAttribute()->name(), $cut)
                ->setAttribute($model->parentAttribute()->name(), $parentId);

            if ($model->isDirty()) {
                $updated[] = $model;
            }

            ++$cut;
        }

        unset($dictionary[$parentId]);

        return $cut;
    }


    public function fixSubTree(Model $root)
    {
        return $this->fixTree($root);
    }
}
