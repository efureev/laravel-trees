<?php

namespace Fureev\Trees;

use Illuminate\Database\Eloquent\Collection as BaseCollection;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TKey of array-key
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends \Illuminate\Support\Collection<TKey, TModel>
 */
class Collection extends BaseCollection
{
    private bool $linked = false;

    private bool $handledToTree = false;

    private int $totalCount = 0;

    public function setToTree(int $count): static
    {
        $this->handledToTree = true;
        $this->totalCount    = $count;

        return $this;
    }

    /**
     * Build a tree from a list of nodes. Each item will have set children relation.
     *
     * If `$fromNode` is provided, the tree will contain only descendants of that node.
     * If `$fillMissingIntermediateNodes` is provided, the tree will get missing intermediate nodes from database.
     *
     * @param Model|string|int|null $fromNode
     * @param bool $setParentRelations Set `parent` into child's relations
     *
     * @return $this
     */
    public function toTree(Model|string|int|null $fromNode = null, bool $setParentRelations = false): self
    {
        if ($this->handledToTree) {
            return $this;
        }

        if ($this->isEmpty()) {
            return new static();
        }

        $this->linkNodes($setParentRelations);
        $items = [];

        if ($fromNode) {
            if ($fromNode instanceof Model) {
                $fromNode = $fromNode->getKey();
            }
        }

        /** @var Model|NestedSetTrait $node */
        foreach ($this->items as $node) {
            if ($node->parentValue() === $fromNode) {
                $items[] = $node;
            }
        }
        return (new static($items))->setToTree($this->count());
    }

    public function toOutput(array $extraColumns = [], $output = null, $offset = "   "): void
    {
        Table::fromTree($this->toTree())
            ->setOffset($offset)
            ->setExtraColumns($extraColumns)
            ->draw($output);
    }


    /**
     * Fill `parent` and `children` relationships for every node in the collection.
     *
     * This will overwrite any previously set relations.
     *
     * Для того, что бы не делать лишние запросы в бд по этим релейшенам
     *
     * @param bool $setParentRelations Set `parent` into child's relations
     *
     * @return $this
     */
    public function linkNodes(bool $setParentRelations = true): self
    {
        if ($this->linked) {
            return $this;
        }

        if ($this->isEmpty()) {
            return $this;
        }

        $groupedNodes = $this->groupBy($this->first()->parentAttribute()->name());

        /** @var NestedSetTrait|Model $node */
        foreach ($this->items as $node) {
            if (!$node->parentValue()) {
                $node->setRelation('parent', null);
            }

            $children = $groupedNodes->get($node->getKey(), []);
            if ($setParentRelations) {
                /** @var Model|NestedSetTrait $child */
                foreach ($children as $child) {
                    $child->setRelation('parent', $node);
                }
            }

            $node->setRelation('children', static::make($children));
        }

        $this->linked = true;

        return $this;
    }

    /**
     * Returns all root-nodes
     *
     * @return $this
     */
    public function getRoots(): self
    {
        return $this->filter(
            static function ($item) {
                return $item->parentValue() === null;
            }
        );
    }

    public function totalCount(): int
    {
        return $this->totalCount;
    }


    /**
     * Add items that are not in the collection but are intermediate nodes
     */
    public function fillMissingIntermediateNodes(): void
    {
        $nodeIds    = $this->pluck('id', 'id')->all();
        $collection = $this->sortByDesc(static fn($item) => $item->levelValue());

        foreach ($collection as $node) {
            if (!$node instanceof Model || $node->isRoot() || isset($nodeIds[$node->parentValue()])) {
                continue;
            }
            /** @var Collection $parents */
            $parents = $node->parentsBuilder()
                ->whereNotIn($node->getKeyName(), $nodeIds)
                ->get();

            $this->items = array_merge($this->items, $parents->all());
            $nodeIds     = array_merge($parents->pluck('id', 'id')->all(), $nodeIds);
        }
    }

    /**
     * @param Model|string|int|null $fromNode
     *
     * @return $this
     */
    public function toBreadcrumbs(Model|string|int|null $fromNode = null): static
    {
        $this->fillMissingIntermediateNodes();

        return $this->toTree($fromNode);
    }
}
