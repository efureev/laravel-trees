<?php

namespace Fureev\Trees;

use Fureev\Trees\Exceptions\{DeleteRootException,
    Exception,
    NotSupportedException,
    TreeNeedValueException,
    UniqueRootException};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Expression;

/**
 * Trait NestedSetTrait
 *
 * @property Model $parent
 * @property Collection|Model[] $children
 * @method QueryBuilder newQuery()
 * @method QueryBuilder query()
 * @mixin Model
 * @mixin QueryBuilder
 */
trait NestedSetTrait
{
    use BaseNestedSetTrait;

    /** @var int */
    protected $treeChange;

    /**
     * @param int|null $tree
     *
     * @return $this
     */
    public function makeRoot(?int $tree = null): self
    {
        $this->operation = Config::OPERATION_MAKE_ROOT;

        if ($tree) {
            $this->setTree($tree);
        }

        return $this;
    }

    /**
     * Set treeID to model
     *
     * @param int|string $treeId
     *
     * @return $this
     */
    public function setTree($treeId): self
    {
        $this->setAttribute($this->getTreeAttributeName(), $treeId);

        return $this;
    }

    /**
     * @param Model $node
     *
     * @return $this
     */
    public function prependTo(Model $node): self
    {
        $this->operation = Config::OPERATION_PREPEND_TO;
        $this->node = $node;

        return $this;
    }

    /**
     * @param Model $node
     *
     * @return $this
     */
    public function appendTo($node): self
    {
        $this->operation = Config::OPERATION_APPEND_TO;
        $this->node = $node;

        return $this;
    }

    /**
     * @param Model $node
     *
     * @return $this
     */
    public function insertBefore($node): self
    {
        $this->operation = Config::OPERATION_INSERT_BEFORE;
        $this->node = $node;

        return $this;
    }

    /**
     * @param Model $node
     *
     * @return $this
     */
    public function insertAfter($node): self
    {
        $this->operation = Config::OPERATION_INSERT_AFTER;
        $this->node = $node;

        return $this;
    }

    /**
     * @return bool
     */
    public function isRoot(): bool
    {
        return $this->getParentId() === null;
    }

    /**
     * @return QueryBuilder|Model|static|object|null
     */
    public function getRoot()
    {
        return $this->newQuery()
            ->root()
            ->first();
    }

    /**
     * @throws \Exception
     */
    public function beforeInsert(): void
    {
        $this->nodeRefresh();

        if (!$this->operation && $this->getAttributeFromArray('_setRoot')) {
            $this->operation = Config::OPERATION_MAKE_ROOT;
            unset($this->attributes['_setRoot']);
        }

        switch ($this->operation) {
            case Config::OPERATION_MAKE_ROOT:

                if (($exist = $this->root()->first()) !== null) {
                    throw new UniqueRootException($exist);
                }

                $this->validateAndSetTreeID();

                $this->setAttribute($this->getLeftAttributeName(), 1);
                $this->setAttribute($this->getRightAttributeName(), 2);
                $this->setAttribute($this->getLevelAttributeName(), 0);

                break;
            case Config::OPERATION_PREPEND_TO:
                $this->validateExisted();
                $this->insertNode($this->node->getLeftOffset() + 1, 1);
                break;
            case Config::OPERATION_APPEND_TO:
                $this->validateExisted();
                $this->insertNode($this->node->getRightOffset(), 1);
                break;
            case Config::OPERATION_INSERT_BEFORE:
                $this->validateExisted();
                $this->insertNode($this->node->getLeftOffset());
                break;
            case Config::OPERATION_INSERT_AFTER:
                $this->validateExisted();
                $this->insertNode($this->node->getRightOffset() + 1);
                break;
            default:
                throw new NotSupportedException(null, 'Method "' . get_class($this) . '::insert" is not supported for inserting new nodes.');
        }
    }

    /**
     * @throws Exception
     */
    protected function validateExisted(): void
    {
        if (!$this->node->exists) {
            throw new Exception('Can not manipulate a node when the target node is new record.');
        }
    }

    /**
     * clear all states
     */
    public function afterInsert(): void
    {
        $this->operation = null;
        $this->node = null;
    }

    /**
     * @throws DeleteRootException
     */
    public function beforeDelete(): void
    {
        if ($this->operation !== Config::OPERATION_DELETE_ALL && $this->isRoot()) {
            throw new DeleteRootException($this);
        }

        $this->refresh();
    }

    /**
     *
     */
    public function afterDelete(): void
    {
        $left = $this->getLeftOffset();
        $right = $this->getRightOffset();

        if ($this->operation === Config::OPERATION_DELETE_ALL || $this->isLeaf()) {
            $this->shift($right + 1, null, $left - $right - 1);
        } else {
            $query = $this->newNestedSetQuery()->descendants();

            $query->update([
                $this->getLeftAttributeName() => new Expression($this->getLeftAttributeName() . '- 1'),
                $this->getRightAttributeName() => new Expression($this->getRightAttributeName() . '- 1'),
                $this->getLevelAttributeName() => new Expression($this->getLevelAttributeName() . '- 1'),
            ]);

            $this->shift($right + 1, null, -2);
        }

        $this->operation = null;
        $this->node = null;
    }


    /**
     * @throws Exception
     */
    public function beforeUpdate(): void
    {
        $this->nodeRefresh();

        switch ($this->operation) {
            case Config::OPERATION_MAKE_ROOT:
                if (!$this->isMultiTree()) {
                    throw new Exception('Can not move a node as the root when Model is not set to "MultiTree"');
                }

                if ($this->getOriginal($this->getTreeAttributeName()) !== $this->getTree()) {
                    $this->treeChange = $this->getTree();
//dd($this->treeChange);
                    $this->setAttribute($this->getTreeAttributeName(), $this->getOriginal($this->getTreeAttributeName()));
                }
                break;


            case Config::OPERATION_INSERT_BEFORE:
            case Config::OPERATION_INSERT_AFTER:
                if ($this->node->isRoot()) {
                    throw new UniqueRootException($this->node, 'Can not move a node before/after root.');
                }
            case Config::OPERATION_PREPEND_TO:
            case Config::OPERATION_APPEND_TO:
                if ($this->equalTo($this->node)) {
                    throw new Exception('Can not move a node when the target node is same.');
                }
                if ($this->node->isChildOf($this)) {
                    throw new Exception('Can not move a node when the target node is child.');
                }
        }
    }

    /**
     * @throws TreeNeedValueException
     */
    public function afterUpdate(): void
    {
        switch ($this->operation) {
            case Config::OPERATION_MAKE_ROOT:
                if ($this->treeChange || $this->exists || !$this->isRoot()) {
                    $this->moveNodeAsRoot();
                }
                break;
            case Config::OPERATION_PREPEND_TO:
                $this->moveNode($this->node->getLeftOffset() + 1, 1);
                break;
            case Config::OPERATION_APPEND_TO:
                $this->moveNode($this->node->getRightOffset(), 1);
                break;
            case Config::OPERATION_INSERT_BEFORE:
                $this->moveNode($this->node->getLeftOffset());
                break;
            case Config::OPERATION_INSERT_AFTER:
                $this->moveNode($this->node->getRightOffset() + 1);
                break;
        }

        $this->operation = null;
        $this->node = null;
        $this->treeChange = null;

        if ($this->forceSave) {
            $this->forceSave = false;
        }
    }


    public function beforeSave(): void
    {
        switch ($this->operation) {
            case Config::OPERATION_PREPEND_TO:
            case Config::OPERATION_APPEND_TO:
                $this->setAttribute($this->getParentIdName(), $this->node->getKey());
                break;
            case Config::OPERATION_INSERT_AFTER:
            case Config::OPERATION_INSERT_BEFORE:
                $this->setAttribute($this->getParentIdName(), $this->node->getParentId());
                break;
        }
    }

    /**
     * @var \Carbon\Carbon
     */
    public static $deletedAt;

    /**
     * Sign on model events.
     */
    public static function bootNestedSetTrait(): void
    {
        static::creating(static function ($model) {
            /** @var NestedSetTrait $model */
            $model->beforeInsert();
        });

        static::created(static function ($model) {
            /** @var NestedSetTrait $model */
            $model->afterInsert();
        });

        static::updating(static function ($model) {
            /** @var NestedSetTrait $model */
            $model->beforeUpdate();
        });

        static::updated(static function ($model) {
            /** @var NestedSetTrait $model */
            $model->afterUpdate();
        });

        static::saving(static function ($model) {
            /** @var NestedSetTrait $model */
            $model->beforeSave();
        });

        static::deleting(static function ($model) {
            // We will need fresh data to delete node safely
            /** @var NestedSetTrait $model */
            $model->beforeDelete();
        });

        static::deleted(static function ($model) {
            /** @var NestedSetTrait $model */
            $model->afterDelete();
        });

        if (static::isSoftDelete()) {
            static::restoring(static function ($model) {
                static::$deletedAt = $model->{$model->getDeletedAtColumn()};
            });

            static::restored(static function ($model) {
                $model->restoreDescendants(static::$deletedAt);
            });
        }
    }

    /**
     * @param Model|self $node
     *
     * @return bool
     */
    public function isChildOf(Model $node): bool
    {
        return ($this->isMultiTree() ? $this->getTree() === $node->getTree() : true) &&
            $this->getLeftOffset() > $node->getLeftOffset() &&
            $this->getRightOffset() < $node->getRightOffset();
    }

    /**
     * Is leaf Node
     *
     * @return bool
     */
    public function isLeaf(): bool
    {
        return $this->getRightOffset() - $this->getLeftOffset() === 1;
    }

    /**
     * @param Model|NestedSetTrait $model
     *
     * @return bool
     */
    public function equalTo(Model $model): bool
    {
        return
            $this->getLeftOffset() === $model->getLeftOffset() &&
            $this->getRightOffset() === $model->getRightOffset() &&
            $this->getLevel() === $model->getLevel() &&
            $this->getParentId() === $model->getParentId() &&
            ($this->isMultiTree()
                ? $this->getTree() === $model->getTree()
                : true
            );
    }


    /**
     * Relation to the parent.
     *
     * @return BelongsTo
     */
    public function parent(): BelongsTo
    {
        return $this
            ->belongsTo(get_class($this), $this->getParentIdName())
            ->setModel($this);
    }

    /**
     * @param int|null $level
     *
     * @return QueryBuilder[]|Collection
     */
    public function parents($level = null)
    {
        return $this
            ->newQuery()
            ->parents($level)
            ->get();
    }

    /**
     * Relation to children
     * Прямые потомки
     *
     * @return HasMany
     */
    public function children(): HasMany
    {
        return $this
            ->hasMany(get_class($this), $this->getParentIdName())
            ->setModel($this);
    }

    /**
     * Get query for descendants of the node.
     *
     * @return DescendantsRelation
     */
    public function descendantsNew(): DescendantsRelation
    {
        return new DescendantsRelation($this->newQuery(), $this);
    }

    /**
     * @return false|int
     */
    public function deleteWithChildren()
    {
        $this->operation = Config::OPERATION_DELETE_ALL;

        if ($this->fireModelEvent('deleting') === false) {
            return false;
        }

        // $result = $this->newQuery()->descendants(null, true)->delete();
        $result = $this->newQuery()->descendants(null, true)->forceDelete();

        $this->fireModelEvent('deleted', false);

        return $result;
    }


    /**
     * Restore the descendants.
     *
     * @param $deletedAt
     */
    protected function restoreDescendants($deletedAt): void
    {
        $this->newNestedSetQuery()
            ->descendants(null, true)
            ->where($this->getDeletedAtColumn(), '>=', $deletedAt)
            ->restore();
    }

    /**
     * @param int $to Left attribute
     * @param int $depth
     *
     * @throws Exception
     */
    protected function insertNode($to, $depth = 0): void
    {
        if ($depth === 0 && $this->node->isRoot()) {
            throw new UniqueRootException($this->node, 'Can not insert a node before/after root.');
        }
        $this->setAttribute($this->getLeftAttributeName(), $to);
        $this->setAttribute($this->getRightAttributeName(), $to + 1);
        $this->setAttribute($this->getLevelAttributeName(), $this->node->getLevel() + $depth);

        if ($this->isMultiTree()) {
            $this->setAttribute($this->getTreeAttributeName(), $this->node->getTree());
        }

        $this->shift($to, null, 2);
    }

    /**
     * @param int $to Left attribute
     * @param int $depth
     */
    protected function moveNode($to, $depth = 0): void
    {
        $left = $this->getLeftOffset();
        $right = $this->getRightOffset();
        $depth = $this->getLevel() - $this->node->getLevel() - $depth;

        if (!$this->isMultiTree() || $this->getTree() === $this->node->getTree()) {
            // same root
            $this->newQuery()
                ->descendants(null, true)
                ->update([
                    $this->getLevelAttributeName() => new Expression("-{$this->getLevelAttributeName()} + " . $depth),
                ]);

            $delta = $right - $left + 1;

            if ($left >= $to) {
                $this->shift($to, $left - 1, $delta);
                $delta = $to - $left;
            } else {
                $this->shift($right + 1, $to - 1, -$delta);
                $delta = $to - $right - 1;
            }

            $this->newQuery()
                ->descendants(null, true)
                ->where($this->getLevelAttributeName(), '<', 0)
                ->update([
                    $this->getLeftAttributeName() => new Expression($this->getLeftAttributeName() . ' + ' . $delta),
                    $this->getRightAttributeName() => new Expression($this->getRightAttributeName() . ' + ' . $delta),
                    $this->getLevelAttributeName() => new Expression("-{$this->getLevelAttributeName()}"),
                ]);
        } else {
            // move from other root
            $tree = $this->node->getTree();
            $this->shift($to, null, $right - $left + 1, $tree);
            $delta = $to - $left;

            $this->newQuery()
                ->descendants(null, true)
                ->update([
                    $this->getLeftAttributeName() => new Expression($this->getLeftAttributeName() . ' + ' . $delta),
                    $this->getRightAttributeName() => new Expression($this->getRightAttributeName() . ' + ' . $delta),
                    $this->getLevelAttributeName() => new Expression($this->getLevelAttributeName() . ' + ' . -$depth),
                    $this->getTreeAttributeName() => $tree,
                ]);

            $this->shift($right + 1, null, $left - $right - 1);
        }

    }

    /**
     * @throws TreeNeedValueException
     */
    protected function moveNodeAsRoot(): void
    {
        $left = $this->getLeftOffset();
        $right = $this->getRightOffset();
        $depth = $this->getLevel();


        if (!$this->getTreeConfig()->isAutogenerateTreeId()) {
            throw new TreeNeedValueException();
        }

        $tree = $this->treeChange ?: $this->getTreeConfig()->generateTreeId($this);

        $this->newQuery()
            ->descendants(null, true)
            ->update([
                $this->getLeftAttributeName() => new Expression($this->getLeftAttributeName() . ' + ' . 1 - $left),
                $this->getRightAttributeName() => new Expression($this->getRightAttributeName() . ' + ' . 1 - $left),
                $this->getLevelAttributeName() => new Expression($this->getLevelAttributeName() . ' + ' . -$depth),
                $this->getTreeAttributeName() => $tree,
            ]);

        $this->shift($right + 1, null, $left - $right - 1);
    }


    /**
     * Move node up
     *
     * @return bool
     */
    public function up(): bool
    {
        $prev = $this->prevSibling()->first();

        if (!$prev) {
            return false;
        }

        return $this->insertBefore($prev)->forceSave();
    }

    /**
     * Move node down.
     *
     * @return bool
     */
    public function down(): bool
    {
        $next = $this->nextSibling()->first();

        if (!$next) {
            return false;
        }

        return $this->insertAfter($next)->forceSave();
    }

    /**
     * @param int $from
     * @param int $to
     * @param int $delta
     * @param int|null $tree
     */
    protected function shift($from, $to, $delta, $tree = null): void
    {
        if ($delta !== 0 && ($to === null || $to >= $from)) {
            if ($tree === null && $this->isMultiTree()) {
                $tree = $this->getTree();
            }

            foreach ([$this->getLeftAttributeName(), $this->getRightAttributeName()] as $i => $attribute) {

                $query = $this->query();
                if ($this->isMultiTree()) {
                    $query->where($this->getTreeAttributeName(), $tree);
                }

                if ($to !== null) {
                    $query->whereBetween($attribute, [$from, $to]);
                } else {
                    $query->where($attribute, '>=', $from);
                }

                $query->update([
                    $attribute => new Expression($attribute . '+ ' . $delta),
                ]);
            }
        }
    }

    /**
     * @throws TreeNeedValueException
     */
    protected function validateAndSetTreeID(): void
    {
        if (!$this->isMultiTree() || $this->getTree() !== null) {
            return;
        }

        if ($this->getTreeConfig()->isAutogenerateTreeId()) {
            $this->setTree($this->getTreeConfig()->generateTreeId($this));

            return;
        }

        throw new TreeNeedValueException();
    }


    /**
     * {@inheritdoc}
     *
     * @return Collection
     */
    public function newCollection(array $models = []): Collection
    {
        return new Collection($models);
    }

    /**
     * Populate children relations for self and all descendants
     *
     * @param int $depth = null
     * @param mixed $with = null. Set the relationships that should be eager loaded.
     *
     * @return static
     */
    public function populateTree($depth = null, $with = null)
    {
        $query = $this->descendants($depth);
        if ($with) {
            $query->with($with);
        }

        $nodes = $query->get();

        $key = $this->getLeftOffset();
        $relates = [];
        $parents = [$key];
        $prev = $this->getLevel();

        /** @var Model|NestedSetTrait $node */
        foreach ($nodes as $node) {
            $level = $node->getLevel();
            if ($level <= $prev) {
                $parents = array_slice($parents, 0, $level - $prev - 1);
            }
            $key = end($parents);
            if (!isset($relates[$key])) {
                $relates[$key] = [];
            }
            $relates[$key][] = $node;
            $parents[] = $node->getLeftOffset();
            $prev = $level;
        }

        $ownerDepth = $this->getLevel();
        $nodes[] = $this;

        foreach ($nodes as $node) {
            $key = $node->getLeftOffset();
            if (isset($relates[$key])) {
                $node->populateRelation('children', $relates[$key]);
            } else if ($depth === null || $ownerDepth + $depth > $node->getAttribute($this->depthAttribute)) {
                $node->setRelation('children', []);
            }
        }

        return $this;
    }

}
