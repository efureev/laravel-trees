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
            ->whereNull($this->model->parentAttribute()->name());

        return $this;
    }

    /**
     * @return $this
     */
    public function treeCondition(): self
    {
        if ($this->model->isMultiTree() && ($val = $this->model->treeValue()) !== null) {
            $this->query->where($this->model->treeAttribute()->name(), $val);
        }

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
            ->whereNotNull($this->model->parentAttribute()->name());

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
            [
                $this->model->leftAttribute()->name(),
                '<',
                $this->model->leftOffset(),
            ],
            [
                $this->model->rightAttribute()->name(),
                '>',
                $this->model->rightOffset(),
            ],
        ];
        if ($level !== null) {
            $condition[] = [
                $this->model->levelAttribute()->name(),
                '>=',
                $level,
            ];
        }

        return $this
            ->where($condition)
            ->treeCondition()
            ->defaultOrder();
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
        $this->query->orderBy($this->model->leftAttribute()->name(), $dir);

        return $this;
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
            ->where($this->model->parentAttribute()->name(), '=', $this->model->parentValue());
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
            ->where($this->model->parentAttribute()->name(), '=', $this->model->parentValue());
    }

    /**
     * Get query for nodes before current node in reversed order.
     *
     * @return QueryBuilder
     */
    public function prevNodes(): self
    {
        return $this
            ->where($this->model->leftAttribute()->name(), '<', $this->model->leftOffset())
            ->treeCondition();
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
            ->where($this->model->parentAttribute()->name(), $this->model->parentValue());
    }

    /**
     * Prev node
     *
     * @return QueryBuilder
     */
    public function prev(): self
    {
        return $this
            ->where($this->model->rightAttribute()->name(), '=', ($this->model->leftOffset() - 1))
            ->treeCondition();
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
            ->where($this->model->parentAttribute()->name(), $this->model->parentValue());
    }

    /**
     * Next node
     *
     * @return QueryBuilder
     */
    public function next(): self
    {
        return $this
            ->where($this->model->leftAttribute()->name(), '=', ($this->model->rightOffset() + 1))
            ->treeCondition();
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
            ->where($this->model->parentAttribute()->name(), '=', $this->model->parentValue());
    }

    /**
     * Get query for nodes after current node.
     *
     * @return QueryBuilder
     */
    public function nextNodes(): self
    {
        return $this
            ->where($this->model->leftAttribute()->name(), '>', $this->model->leftOffset())
            ->treeCondition();
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
     * @return QueryBuilder
     */
    public function leaf(): self
    {
        return $this
            ->where(
                $this->model->leftAttribute()->name(),
                '=',
                new Expression($this->model->rightAttribute()->name() . ' - 1')
            )
            ->treeCondition();
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
        $attribute = $backOrder ? $this->model->rightAttribute()->name() : $this->model->leftAttribute()->name();

        $condition = [
            [
                $attribute,
                $andSelf ? '>=' : '>',
                $this->model->leftOffset(),
            ],
            [
                $attribute,
                $andSelf ? '<=' : '<',
                $this->model->rightOffset(),
            ],
        ];

        if ($level !== null) {
            $condition[] = [
                $this->model->levelAttribute()->name(),
                '<=',
                ($this->model->levelValue() + $level),
            ];
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
        [
            $left,
            $right,
        ] = $values;

        $this->query
            ->whereBetween(
                $this->model->getTable() . '.' . $this->model->leftAttribute()->name(),
                [
                    $left,
                    $right,
                ],
                $boolean,
                $not
            );

        if ($this->model->isMultiTree()) {
            $treeId = end($values);
            $this->query->where($this->model->getTable() . '.' . $this->model->treeAttribute()->name(), $treeId);
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

        $columns = $this->model->getTreeConfig()->columns();

        $data = $query->first($columns);
        if (!$data && $required) {
            throw new ModelNotFoundException("Model #$id not found");
        }

        return (array)$data;
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
     * @param int|string $treeId
     *
     * @return $this
     */
    public function byTree($treeId): self
    {
        if ($this->model->isMultiTree()) {
            $this->query->where($this->model->treeAttribute()->name(), $treeId);
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
            $this->query->where($this->model->levelAttribute()->name(), '<=', $level);
        }

        return $this;
    }

    /**
     * @param int|null $level
     *
     * @return $this
     */
    public function byLevel(?int $level): self
    {
        if ($level !== null) {
            $this->query->where($this->model->levelAttribute()->name(), $level);
        }

        return $this;
    }

    /**
     * Get wrapped column names.
     *
     * @return array
     */
    protected function wrappedColumns(): array
    {
        $grammar = $this->query->getGrammar();

        return array_map(
            static function ($col) use ($grammar) {
                return $grammar->wrap($col);
            },
            $this->model->getTreeConfig()->columns()
        );
    }
}
