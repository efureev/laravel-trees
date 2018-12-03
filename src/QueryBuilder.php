<?php

namespace Fureev\Trees;


use Illuminate\Database\Eloquent\Builder;

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
    public function whereIsRoot()
    {
        $this->query->whereNull($this->model->getParentIdName());

        return $this;
    }

    /**
     * Exclude root node from the result.
     *
     * @return $this
     */
    public function withoutRoot()
    {
        $this->query->whereNotNull($this->model->getParentIdName());

        return $this;
    }

    /**
     * @param int|null $level
     *
     * @return \Fureev\Trees\QueryBuilder
     */
    public function parents($level = null)
    {

        $condition = [
            [$this->model->getLeftAttributeName(), '<', $this->model->getLeftOffset()],
            [$this->model->getRightAttributeName(), '>', $this->model->getRightOffset()],
        ];
        if ($level !== null) {
            $condition[] = [$this->model->getLevelAttributeName(), '>=', $this->model->getLevel()];
        }

        return $this
            ->where($condition)
            ->defaultOrder();
    }

    /**
     * Get wrapped `lft` and `rgt` column names.
     *
     * @return array
     */
    protected function wrappedColumns()
    {
        $grammar = $this->query->getGrammar();

        return [
            $grammar->wrap($this->model->getLeftAttributeName()),
            $grammar->wrap($this->model->getRightAttributeName()),
        ];
    }

    /**
     * Order by node position.
     *
     * @param string $dir
     *
     * @return $this
     */
    public function defaultOrder($dir = 'asc')
    {
        $this->query->orders = null;
        $this->query->orderBy($this->model->getLeftAttributeName(), $dir);

        return $this;
    }


}
