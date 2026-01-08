<?php

declare(strict_types=1);

namespace Fureev\Trees;

use Fureev\Trees\Config\Helper;
use Fureev\Trees\Exceptions\Exception;
use Illuminate\Database\Eloquent\Collection as BaseCollection;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TKey of array-key
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends BaseCollection<TKey, TModel>
 */
class Collection extends BaseCollection
{
    private bool $linked = false;

    private bool $handledToTree = false;

    private int $totalCount = 0;

    /**
     * Returns all root nodes (nodes without a parent).
     *
     * @return static<TKey, TModel>
     */
    public function getRoots(): static
    {
        return $this->filter(static fn(Model $item) => /** @var UseTree $item */ $item->parentValue() === null);
    }

    public function totalCount(): int
    {
        return $this->totalCount;
    }

    protected function setToTree(int $count): static
    {
        $this->handledToTree = true;
        $this->totalCount    = $count;

        return $this;
    }

    /**
     * Checks if the collection is empty or has already been processed.
     *
     * @return bool True if the collection can skip further processing
     */
    private function shouldSkipProcessing(bool $checkTreeHandled = false): bool
    {
        if ($this->isEmpty()) {
            return true;
        }

        if ($checkTreeHandled && $this->handledToTree) {
            return true;
        }

        return false;
    }

    /**
     * Validates that the first model in the collection is a tree node.
     *
     * @throws Exception When the model is not a tree node
     */
    private function validateTreeNode(): void
    {
        $model = $this->first();
        if (!Helper::isTreeNode($model)) {
            throw new Exception('Model should be a Tree Node');
        }
    }


    /**
     * Build a tree from a list of nodes. Each node will have its children relation set.
     *
     * @param Model|string|int|null $fromNode Starting node key or instance (null for all roots)
     * @param bool $setParentRelations Whether to set parent relations on children
     *
     * @return static<TKey, TModel> Collection with tree structure
     */
    public function toTree(Model|string|int|null $fromNode = null, bool $setParentRelations = false): static
    {
        if ($this->shouldSkipProcessing(true)) {
            return $this;
        }

        $this->linkNodes($setParentRelations);

        $items = [];

        if ($fromNode instanceof Model) {
            $fromNode = $fromNode->getKey();
        }

        /** @var Model|UseConfigShorter $node */
        foreach ($this->items as $node) {
            if ($node->parentValue() === $fromNode) {
                $items[] = $node;
            }
        }

        return (new static($items))->setToTree($this->count());
    }


    /**
     * Fill parent and children relationships for every node in the collection.
     *
     * @param bool $setParentRelations Whether to set parent relations on children
     *
     * @return static<TKey, TModel>
     * @throws Exception When a model is not a tree node
     */
    public function linkNodes(bool $setParentRelations = true): static
    {
        if ($this->linked || $this->isEmpty()) {
            return $this;
        }

        $this->validateTreeNode();

        /** @var UseTree&Model $firstModel */
        $firstModel      = $this->first();
        $groupedByParent = $this->groupBy($firstModel->parentAttribute());

        /** @var UseTree&Model $node */
        foreach ($this->items as $node) {
            // Set parent relation
            if (!$node->parentValue()) {
                $node->setRelation('parent', null);
            }

            // Set children relation
            $childNodes = $groupedByParent->get($node->getKey(), []);
            $node->setRelation('children', static::make($childNodes));

            // Set parent relation on children if requested
            if ($setParentRelations) {
                /** @var UseTree&Model $child */
                foreach ($childNodes as $child) {
                    $child->setRelation('parent', $node);
                }
            }
        }

        $this->linked = true;

        return $this;
    }


    /**
     * Add missing intermediate nodes to the collection.
     */
    public function fillMissingIntermediateNodes(): void
    {
        $existingNodeIds = $this->pluck('id', 'id')->all();
        $sortedNodes     = $this->sortByDesc(static fn($item) => $item->levelValue());

        /** @var Model&UseTree $node */
        foreach ($sortedNodes as $node) {
            if (!$node instanceof Model || $node->isRoot() || isset($existingNodeIds[$node->parentValue()])) {
                continue;
            }

            /** @var Collection $missingParents */
            $missingParents = $node->parentsBuilder()
                ->whereNotIn($node->getKeyName(), $existingNodeIds)
                ->get();

            $this->items     = array_merge($this->items, $missingParents->all());
            $existingNodeIds = array_merge($missingParents->pluck('id', 'id')->all(), $existingNodeIds);
        }
    }

    /**
     * Builds a breadcrumb path to a specific node.
     *
     * @param Model|string|int|null $fromNode The target node
     *
     * @return static<TKey, TModel>
     */
    public function toBreadcrumbs(Model|string|int|null $fromNode = null): static
    {
        $this->fillMissingIntermediateNodes();

        return $this->toTree($fromNode);
    }
}
