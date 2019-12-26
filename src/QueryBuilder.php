<?php

namespace Fureev\Trees;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Expression;

/**
 * Class QueryBuilder
 *
 * @package Fureev\Trees
 */
class QueryBuilder extends Builder
{
    /**
     * @var Model|NestedSetTrait
     */
    protected $model;

    /**
     * Scope limits query to select just root node.
     *
     * @return $this
     */
    public function root(): self
    {
        $this
            ->treeCondition()
            ->whereNull($this->model->getParentIdName());

        return $this;
    }

    /**
     * Exclude root node from the result.
     *
     * @return $this
     */
    public function notRoot(): self
    {
        $this
            ->treeCondition()
            ->whereNotNull($this->model->getParentIdName());

        return $this;
    }

    /**
     * @param int|null $level
     *
     * @return QueryBuilder
     */
    public function parents(int $level = null): self
    {
        $condition = [
            [$this->model->getLeftAttributeName(), '<', $this->model->getLeftOffset()],
            [$this->model->getRightAttributeName(), '>', $this->model->getRightOffset()],
        ];
        if ($level !== null) {
            $condition[] = [$this->model->getLevelAttributeName(), '>=', $level];
        }

        return $this
            ->where($condition)
            ->treeCondition()
            ->defaultOrder();
    }

    /**
     * Get all siblings
     *
     * @return QueryBuilder
     */
    public function siblings(): self
    {
        return $this
            ->siblingsAndSelf()
            ->where($this->model->getKeyName(), '<>', $this->model->getKey());
    }

    /**
     * @return QueryBuilder
     */
    public function siblingsAndSelf(): self
    {
        return $this
            ->where($this->model->getParentIdName(), '=', $this->model->getParentId());
    }

    /**
     * Prev node
     *
     * @return QueryBuilder
     */
    public function prev(): self
    {
        return $this
            ->where($this->model->getRightAttributeName(), '=', $this->model->getLeftOffset() - 1)
            ->treeCondition();
    }

    /**
     * Next node
     *
     * @return QueryBuilder
     */
    public function next(): self
    {
        return $this
            ->where($this->model->getLeftAttributeName(), '=', $this->model->getRightOffset() + 1)
            ->treeCondition();
    }

    /**
     * Get query for siblings before the node.
     *
     * @return QueryBuilder
     */
    public function prevSiblings(): self
    {
        return $this
            ->prevNodes()
            ->where($this->model->getParentIdName(), '=', $this->model->getParentId());
    }

    /**
     * Get query for sibling before the node.
     *
     * @return QueryBuilder
     */
    public function prevSibling(): self
    {
        return $this
            ->prev()
            ->where($this->model->getParentIdName(), $this->model->getParentId());
    }

    /**
     * Get query for sibling before the node.
     *
     * @return QueryBuilder
     */
    public function nextSibling(): self
    {
        return $this
            ->next()
            ->where($this->model->getParentIdName(), $this->model->getParentId());
    }

    /**
     * Get query for siblings after the node.
     *
     * @return QueryBuilder
     */
    public function nextSiblings(): self
    {
        return $this
            ->nextNodes()
            ->where($this->model->getParentIdName(), '=', $this->model->getParentId());
    }

    /**
     * Get query for nodes before current node in reversed order.
     *
     * @return QueryBuilder
     */
    public function prevNodes(): self
    {
        return $this
            ->where($this->model->getLeftAttributeName(), '<', $this->model->getLeftOffset())
            ->treeCondition();
    }

    /**
     * Get query for nodes after current node.
     *
     * @return QueryBuilder
     */
    public function nextNodes(): self
    {
        return $this
            ->where($this->model->getLeftAttributeName(), '>', $this->model->getLeftOffset())
            ->treeCondition();
    }

    /**
     * @return QueryBuilder
     */
    public function leaf(): self
    {
        return $this
            ->where(
                $this->model->getLeftAttributeName(),
                '=',
                new Expression($this->model->getRightAttributeName() . ' - 1')
            );
    }

    /**
     * @param int|null $level
     *
     * @return QueryBuilder
     */
    public function leaves(int $level = null): self
    {
        return $this
            ->descendants($level)
            ->leaf();
    }

    /**
     * Get all descendants
     * Потомки
     *
     * @param int|null $level Level of descendants
     * @param bool $andSelf apply this node into a select
     * @param bool $backOrder Order of a select
     *
     * @return QueryBuilder
     */
    public function descendants(?int $level = null, $andSelf = false, $backOrder = false): self
    {
        $attribute = $backOrder ? $this->model->getRightAttributeName() : $this->model->getLeftAttributeName();

        $condition = [
            [$attribute, $andSelf ? '>=' : '>', $this->model->getLeftOffset()],
            [$attribute, $andSelf ? '<=' : '<', $this->model->getRightOffset()],
        ];

        if ($level !== null) {
            $condition[] = [$this->model->getLevelAttributeName(), '<=', $this->model->getLevel() + $level];
        }

        return $this
            ->where($condition)
            ->treeCondition()
            ->orderBy($attribute, $backOrder ? 'desc' : 'asc');
    }

    /**
     * Get all descendants (query version)
     * Потомки
     *
     * @param string|int|Model|NestedSetTrait $id
     * @param string $boolean
     * @param bool $not
     * @param bool $andSelf
     *
     * @return $this
     */
    public function whereDescendantOf($id, $boolean = 'and', $not = false, $andSelf = false): self
    {
        $data = $this->model->getNodeBounds($id);

        // Don't include the node
        if (!$andSelf) {
            ++$data[0];
        }

        return $this->whereNodeBetween($data, $boolean, $not);
    }

    /**
     * Add node selection statement between specified range.
     *
     * @param array $values
     * @param string $boolean
     * @param bool $not
     *
     * @return $this
     * @since 2.0
     *
     */
    public function whereNodeBetween($values, $boolean = 'and', $not = false): self
    {
        [$left, $right] = $values;

        $this->query
            ->whereBetween($this->model->getTable() . '.' . $this->model->getLeftAttributeName(), [$left, $right], $boolean, $not);

        if ($this->model->isMultiTree()) {
            $treeId = end($values);
            $this->query->where($this->model->getTable() . '.' . $this->model->getTreeAttributeName(), $treeId);
        }

        return $this;
    }

    /**
     * Get plain node data.
     *
     * @param mixed $id
     * @param bool $required
     *
     * @return array
     */
    public function getPlainNodeData($id, $required = false): array
    {
        return array_values($this->getNodeData($id, $required));
    }

    /**
     * Get node's `left offset` and `right offset`, `level`, `parent_id`, `tree` tree values.
     *
     * @param string|int $id
     * @param bool $required
     *
     * @return array
     */
    public function getNodeData($id, $required = false): array
    {
        $query = $this->toBase();
        $query->where($this->model->getKeyName(), '=', $id);

        $columns = $this->model->getTreeConfig()->getColumns();

        $data = $query->first($columns);
        if (!$data && $required) {
            throw new ModelNotFoundException("Model #$id not found");
        }

        return (array)$data;
    }


    /**
     * Get wrapped column names.
     *
     * @return array
     */
    protected function wrappedColumns(): array
    {
        $grammar = $this->query->getGrammar();

        return array_map(static function ($col) use ($grammar) {
            return $grammar->wrap($col);
        }, $this->model->getTreeConfig()->getColumns());
    }


    /**
     * Order by node position.
     *
     * @param string $dir
     *
     * @return $this
     */
    public function defaultOrder($dir = 'asc'): self
    {
        $this->query->orders = null;
        $this->query->orderBy($this->model->getLeftAttributeName(), $dir);

        return $this;
    }

    /**
     * @param string|null $table
     *
     * @return $this
     */
    public function applyNestedSetScope($table = null): self
    {
        return $this->model->applyNestedSetScope($this, $table);
    }

    /**
     * @return $this
     */
    public function treeCondition(): self
    {
        if ($this->model->isMultiTree() && $this->model->getTree() !== null) {
            $this->query->where($this->model->getTreeAttributeName(), $this->model->getTree());
        }

        return $this;
    }

    /**
     * @param int $treeId
     *
     * @return $this
     */
    public function byTree(int $treeId): self
    {
        if ($this->model->isMultiTree()) {
            $this->query->where($this->model->getTreeAttributeName(), $treeId);
        }

        return $this;
    }

    /**
     * Returns items from level 0 to level <= {$level}
     *
     * @param int $level
     *
     * @return $this
     */
    public function toLevel(?int $level): self
    {
        if ($level !== null) {
            $this->query->where($this->model->getLevelAttributeName(), '<=', $level);
        }

        return $this;
    }

}
