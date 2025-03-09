<?php

declare(strict_types=1);

namespace Fureev\Trees;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Expression;

/**
 * @template TModel of Model
 *
 * @package Fureev\Trees
 * @method Collection get($columns = ['*'])
 * @method Collection all($columns = ['*'])
 *
 * @property TModel|UseTree $model
 *
 * @extends Builder<TModel>
 */
class QueryBuilderV2 extends Builder
{
    public function treeCondition(): static
    {
        if ($this->model->isMulti() && ($val = $this->model->treeValue()) !== null) {
            $this->query->where((string)$this->model->treeAttribute(), $val);
        }

        return $this;
    }

    /**
     * Scope limits query to select just root node.
     */
    public function root(): static
    {
        $this
            ->treeCondition()
            ->whereNull((string)$this->model->parentAttribute());

        return $this;
    }


    /**
     * Exclude root node from the result.
     */
    public function notRoot(): static
    {
        $this
            ->treeCondition()
            ->whereNotNull((string)$this->model->parentAttribute());

        return $this;
    }

    public function parents(?int $level = null, bool $andSelf = false): static
    {
        $condition = [
            [
                (string)$this->model->leftAttribute(),
                $andSelf ? '<=' : '<',
                $this->model->leftValue(),
            ],
            [
                (string)$this->model->rightAttribute(),
                $andSelf ? '>=' : '>',
                $this->model->rightValue(),
            ],
        ];

        if ($level !== null) {
            $condition[] = [
                (string)$this->model->levelAttribute(),
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
     * Get all descendants
     *
     * @param int|null $level Level of descendants
     * @param bool $andSelf apply this node into a select
     * @param bool $backOrder Order of a select
     */
    public function descendantsQuery(?int $level = null, bool $andSelf = false, bool $backOrder = false): static
    {
        $attribute = $backOrder
            ? (string)$this->model->rightAttribute()
            : (string)$this->model->leftAttribute();

        $condition = [
            [
                $attribute,
                $andSelf ? '>=' : '>',
                $this->model->leftValue(),
            ],
            [
                $attribute,
                $andSelf ? '<=' : '<',
                $this->model->rightValue(),
            ],
        ];

        if ($level !== null) {
            $condition[] = [
                (string)$this->model->levelAttribute(),
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
     *
     * @param string|int|Model|NestedSetTrait $id
     * @param string $boolean
     * @param bool $not
     * @param bool $andSelf
     */
    public function whereDescendantOf(
        Model|string|int $id,
        string $boolean = 'and',
        bool $not = false,
        bool $andSelf = false
    ): static {
        $data = $this->model->getNodeBounds($id);

        // Don't include the node
        if (!$andSelf) {
            ++$data[0];
        }

        return $this->whereNodeBetween($data, $boolean, $not);
    }

    /**
     * @param Model|UseTree $model
     */
    public function whereAncestorOf(Model $model): static
    {
        $condition = [
            [
                (string)$model->leftAttribute(),
                '<',
                $model->leftValue(),
            ],
            [
                (string)$model->rightAttribute(),
                '>',
                $model->rightValue(),
            ],
        ];

        return $this
            ->where($condition)
            ->treeCondition()
            ->defaultOrder();
    }

    public function prev(): static
    {
        return $this
            ->where((string)$this->model->rightAttribute(), '=', ($this->model->leftValue() - 1))
            ->treeCondition();
    }

    /**
     * Get query for nodes before current node in reversed order.
     */
    public function prevNodes(): static
    {
        return $this
            ->where((string)$this->model->leftAttribute(), '<', $this->model->leftValue())
            ->treeCondition();
    }

    public function next(): static
    {
        return $this
            ->where((string)$this->model->leftAttribute(), '=', ($this->model->rightValue() + 1))
            ->treeCondition();
    }

    /**
     * Get query for nodes after current node.
     */
    public function nextNodes(): static
    {
        return $this
            ->where((string)$this->model->leftAttribute(), '>', $this->model->leftValue())
            ->treeCondition();
    }

    /**
     * Get query for sibling before the node.
     */
    public function prevSibling(): static
    {
        return $this->prev()
            ->where((string)$this->model->parentAttribute(), $this->model->parentValue());
    }

    /**
     * Get query for siblings before the node.
     */
    public function prevSiblings(): static
    {
        return $this
            ->prevNodes()
            ->where((string)$this->model->parentAttribute(), '=', $this->model->parentValue());
    }

    /**
     * Get query for sibling before the node.
     */
    public function nextSibling(): static
    {
        return $this->next()
            ->where((string)$this->model->parentAttribute(), $this->model->parentValue());
    }

    /**
     * Get query for siblings after the node.
     */
    public function nextSiblings(): static
    {
        return $this
            ->nextNodes()
            ->where((string)$this->model->parentAttribute(), '=', $this->model->parentValue());
    }

    public function siblings(): static
    {
        return $this
            ->siblingsAndSelf()
            ->where($this->model->getKeyName(), '<>', $this->model->getKey());
    }

    public function siblingsAndSelf(): static
    {
        return $this
            ->where((string)$this->model->parentAttribute(), '=', $this->model->parentValue());
    }

    public function leaves(?int $level = null): static
    {
        return $this
            ->descendantsQuery($level)
            ->leaf();
    }

    public function leaf(): static
    {
        return $this
            ->where(
                (string)$this->model->leftAttribute(),
                '=',
                new Expression($this->model->rightAttribute() . ' - 1')
            )
            ->treeCondition();
    }

    /**
     * @param int $dir SORT_ASC|SORT_DESC
     */
    public function defaultOrder(int $dir = SORT_ASC): static
    {
        $this->query->orders = null;
        $this->query->orderBy((string)$this->model->leftAttribute(), $dir === SORT_ASC ? 'asc' : 'desc');

        return $this;
    }


    /**
     * @return (string|int)[]
     */
    public function getPlainNodeData(string|int $id, bool $required = false): array
    {
        return array_values($this->getNodeData($id, $required));
    }


    /**
     * Get node's `left offset` and `right offset`, `level`, `parent_id`, `tree` tree values.
     *
     * @return (string|int)[]
     */
    public function getNodeData(string|int $id, bool $required = false): array
    {
        $query = $this->toBase();
        $query->where($this->model->getKeyName(), '=', $id);

        $columns = $this->model->getTreeConfig()->columnsNames();

        $data = $query->first($columns);
        if (!$data && $required) {
            throw new ModelNotFoundException("Model [$id] not found");
        }

        return (array)$data;
    }

    /**
     * Add node selection statement between specified range.
     *
     * @param (string|int)[] $values
     */
    public function whereNodeBetween(array $values, string $boolean = 'and', bool $not = false): static
    {
        [
            $left,
            $right,
        ] = $values;

        $this->query
            ->whereBetween(
                "{$this->model->getTable()}.{$this->model->leftAttribute()}",
                [
                    $left,
                    $right,
                ],
                $boolean,
                $not
            );

        if ($this->model->isMulti()) {
            $treeId = end($values);
            $this->query->where("{$this->model->getTable()}.{$this->model->treeAttribute()}", $treeId);
        }

        return $this;
    }

    public function applyNestedSetScope(?string $table = null): static
    {
        return $this->model->applyNestedSetScope($this, $table);
    }

    public function byTree(int|string $treeId): static
    {
        if ($this->model->isMulti()) {
            $this->query->where($this->model->treeAttribute()->columnName(), $treeId);
        }

        return $this;
    }


    /**
     * Returns items from level 0 to level <= $level
     */
    public function toLevel(?int $level): static
    {
        if ($level !== null) {
            $this->query->where((string)$this->model->levelAttribute(), '<=', $level);
        }

        return $this;
    }

    public function byLevel(?int $level): static
    {
        if ($level !== null) {
            $this->query->where((string)$this->model->levelAttribute(), $level);
        }

        return $this;
    }

    public function byParent(int|string|Model|null $parent): static
    {
        if ($parent !== null) {
            $key = $parent instanceof Model ? $parent->getKey() : $parent;

            $this->treeCondition()
                ->where((string)$this->model->parentAttribute(), $key);
        }

        return $this;
    }


    /**
     * Get wrapped column names
     */
    public function wrappedColumns(): array
    {
        $grammar = $this->query->getGrammar();

        return array_map(
            static function ($col) use ($grammar) {
                return $grammar->wrap($col);
            },
            $this->model->getTreeConfig()->columnsNames()
        );
    }

    /**
     * Get a wrapped table name
     */
    public function wrappedTable(): string
    {
        return $this->query->getGrammar()->wrapTable($this->getQuery()->from);
    }

    /**
     * Get a wrapped KeyName
     */
    public function wrappedKey(): string
    {
        return $this->query->getGrammar()->wrap($this->model->getKeyName());
    }
}
