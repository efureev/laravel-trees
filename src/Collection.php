<?php

namespace Fureev\Trees;

use Illuminate\Database\Eloquent\Collection as BaseCollection;
use Illuminate\Database\Eloquent\Model;

class Collection extends BaseCollection
{
    /**
     * Build a tree from a list of nodes. Each item will have set children relation.
     *
     * If `$fromNode` is provided, the tree will contain only descendants of that node.
     * If `$fillMissingIntermediateNodes` is provided, the tree will get missing intermediate nodes from database.
     *
     * @param Model|string|int|null $fromNode
     *
     * @return $this
     */
    public function toTree($fromNode = null, bool $fillMissingIntermediateNodes = false): self
    {
        if ($this->isEmpty()) {
            return new static();
        }

        if ($fillMissingIntermediateNodes) {
            $this->fillMissingIntermediateNodes();
        }

        $this->linkNodes(false);
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

        return new static($items);
    }

    /**
     * Fill `parent` and `children` relationships for every node in the collection.
     *
     * This will overwrite any previously set relations.
     *
     * Для того, что бы не делать лишние запросы в бд по этим релейшенам
     *
     * @param bool $setParentRelations
     *
     * @return $this
     */
    public function linkNodes($setParentRelations = true): self
    {
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

    /**
     * Add items that are not in the collection but are intermediate nodes
     */
    public function fillMissingIntermediateNodes(): void
    {
        $items   = [];
        $nodeIds = $this->pluck('id')->all();

        foreach ($this->items as $node) {
            if ($node instanceof Model && !$node->isRoot() && !in_array($node->parentValue(), $nodeIds, true)) {
                $items[] = $node->parents()
                    ->filter(
                        static fn(Model $model) => ! in_array($model->id, $nodeIds, true)
                    );
            }
        }

        $this->items = array_merge(
            $this->items,
            collect($items)->flatten()->all(),
        );
    }
}
