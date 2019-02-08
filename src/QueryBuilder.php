<?php

namespace Fureev\Trees;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;

/**
 * Class QueryBuilder
 *
 * @package Fureev\Trees
 */
class QueryBuilder extends Builder
{
    /**
     * @var \Fureev\Trees\NestedSetTrait|\Illuminate\Database\Eloquent\Model
     */
    protected $model;

    /**
     * Scope limits query to select just root node.
     *
     * @return $this
     */
    public function root(): self
    {
        $this->query->whereNull($this->model->getParentIdName());

        return $this;
    }

    /**
     * Exclude root node from the result.
     *
     * @return $this
     */
    public function notRoot(): self
    {
        $this->query->whereNotNull($this->model->getParentIdName());

        return $this;
    }

    /**
     * @param int|null $level
     *
     * @return \Fureev\Trees\QueryBuilder
     */
    public function parents(int $level = null): QueryBuilder
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
            ->defaultOrder();
    }

    /**
     * Get all siblings
     *
     * @return \Fureev\Trees\QueryBuilder
     */
    public function siblings(): QueryBuilder
    {
        return $this
            ->siblingsAndSelf()
            ->where($this->model->getKeyName(), '<>', $this->model->getKey());
    }

    /**
     * @return \Fureev\Trees\QueryBuilder
     */
    public function siblingsAndSelf(): QueryBuilder
    {
        return $this
            ->where($this->model->getParentIdName(), '=', $this->model->getParentId());
    }

    /**
     * Prev node
     *
     * @return \Fureev\Trees\QueryBuilder
     */
    public function prev(): QueryBuilder
    {
        return $this
            ->where($this->model->getRightAttributeName(), '=', $this->model->getLeftOffset() - 1);
    }

    /**
     * Next node
     *
     * @return \Fureev\Trees\QueryBuilder
     */
    public function next(): QueryBuilder
    {
        return $this
            ->where($this->model->getLeftAttributeName(), '=', $this->model->getRightOffset() + 1);
    }

    /**
     * Get query for siblings before the node.
     *
     * @return \Fureev\Trees\QueryBuilder
     */
    public function prevSiblings(): QueryBuilder
    {
        return $this->prevNodes()
            ->where($this->model->getParentIdName(), '=', $this->model->getParentId());
    }

    /**
     * Get query for sibling before the node.
     *
     * @return \Fureev\Trees\QueryBuilder
     */
    public function prevSibling(): QueryBuilder
    {
        return $this
            ->prev()
            ->where($this->model->getParentIdName(), $this->model->getParentId());
    }

    /**
     * Get query for sibling before the node.
     *
     * @return \Fureev\Trees\QueryBuilder
     */
    public function nextSibling(): QueryBuilder
    {
        return $this
            ->next()
            ->where($this->model->getParentIdName(), $this->model->getParentId());
    }

    /**
     * Get query for siblings after the node.
     *
     * @return \Fureev\Trees\QueryBuilder
     */
    public function nextSiblings(): QueryBuilder
    {
        return $this->nextNodes()
            ->where($this->model->getParentIdName(), '=', $this->model->getParentId());
    }

    /**
     * Get query for nodes before current node in reversed order.
     *
     * @return \Fureev\Trees\QueryBuilder
     */
    public function prevNodes(): QueryBuilder
    {
        return $this->where($this->model->getLeftAttributeName(), '<', $this->model->getLeftOffset());
    }

    /**
     * Get query for nodes after current node.
     *
     * @return \Fureev\Trees\QueryBuilder
     */
    public function nextNodes(): QueryBuilder
    {
        return $this->where($this->model->getLeftAttributeName(), '>', $this->model->getLeftOffset());
    }

    /**
     * @return \Fureev\Trees\QueryBuilder
     */
    public function leaf(): QueryBuilder
    {
        return $this->where($this->model->getLeftAttributeName(), '=', new Expression($this->model->getRightAttributeName() . ' - 1'));
    }

    /**
     * @param int|null $level
     *
     * @return \Fureev\Trees\QueryBuilder
     */
    public function leaves(int $level = null): QueryBuilder
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
     * @return \Fureev\Trees\QueryBuilder
     */
    public function descendants(int $level = null, $andSelf = false, $backOrder = false): QueryBuilder
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
            ->orderBy($attribute, $backOrder ? 'desc' : 'asc');
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


}
