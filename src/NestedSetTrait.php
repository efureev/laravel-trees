<?php

namespace Fureev\Trees;

use Closure;
use Fureev\Trees\Config\Base;
use Fureev\Trees\Exceptions\{DeletedNodeHasChildrenException,
    DeleteRootException,
    Exception,
    NotSupportedException,
    TreeNeedValueException,
    UniqueRootException
};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Expression;

/**
 * Trait NestedSetTrait
 *
 * @property Model $parent
 * @property Model $parentWithTrashed
 * @property Collection|Model[] $children
 * @method QueryBuilder newQuery()
 * @method QueryBuilder query()
 * @mixin Model
 * @mixin QueryBuilder
 */
trait NestedSetTrait
{
    use BaseNestedSetTrait;

    /**
     * @var \Carbon\Carbon
     */
    public static $deletedAt;

    /**
     * @var integer
     */
    protected $treeChange;

    /**
     * Sign on model events.
     */
    public static function bootNestedSetTrait(): void
    {
        static::creating(
            static function ($model) {
                /** @var NestedSetTrait $model */
                $model->beforeInsert();
            }
        );

        static::created(
            static function ($model) {
                /** @var NestedSetTrait $model */
                $model->afterInsert();
            }
        );

        static::updating(
            static function ($model) {
                /** @var NestedSetTrait $model */
                $model->beforeUpdate();
            }
        );

        static::updated(
            static function ($model) {
                /** @var NestedSetTrait $model */
                $model->afterUpdate();
            }
        );

        static::saving(
            static function ($model) {
                /** @var NestedSetTrait $model */
                $model->beforeSave();
            }
        );

        static::deleting(
            static function ($model) {
                // We will need fresh data to delete node safely
                /** @var NestedSetTrait $model */
                $model->beforeDelete();
            }
        );

        static::deleted(
            static function ($model) {
                /** @var NestedSetTrait $model */
                if ($model::isSoftDelete() && !$model->isForceDeleting()) {
                    return;
                }

                $model->afterDelete();
            }
        );

        if (static::isSoftDelete()) {
            static::restoring(
                static function (Model $model) {
                    $model->beforeRestore();
                }
            );

            static::restored(
                static function (Model $model) {
                    $model->afterRestore();
                }
            );
        }
    }

    public function newQueryWithTrashed(): QueryBuilder
    {
        return $this->newQuery()->when(static::isSoftDelete(), fn($query) => $query->withTrashed());
    }

    /**
     * @throws \Exception
     */
    public function beforeInsert(): void
    {
        $this->nodeRefresh();

        if (!$this->operation) {
            if ($this->parentWithTrashed) {
                $this->saveWithParent();
            } else {
                if ($this->isMultiTree() || $this->getAttributeFromArray('_setRoot')) {
                    $this->saveWithOutTargets();
                }
            }
        }

        switch ($this->operation) {
            case Base::OPERATION_MAKE_ROOT:
                if (!$this->isMultiTree() && ($exist = $this->root()->first()) !== null) {
                    throw new UniqueRootException($exist);
                }

                $this->validateAndSetTreeID();

                $this->setAttribute($this->leftAttribute()->name(), 1);
                $this->setAttribute($this->rightAttribute()->name(), 2);
                $this->setAttribute($this->levelAttribute()->name(), 0);

                break;
            case Base::OPERATION_PREPEND_TO:
                $this->validateExisted();
                $this->insertNode(($this->node->leftOffset() + 1), 1);
                break;
            case Base::OPERATION_APPEND_TO:
                $this->validateExisted();
                $this->insertNode($this->node->rightOffset(), 1);
                break;
            case Base::OPERATION_INSERT_BEFORE:
                $this->validateExisted();
                $this->insertNode($this->node->leftOffset());
                break;
            case Base::OPERATION_INSERT_AFTER:
                $this->validateExisted();
                $this->insertNode($this->node->rightOffset() + 1);
                break;
            default:
                throw new NotSupportedException(
                    null,
                    'Method "' . get_class(
                        $this
                    ) . '::insert" is not supported for inserting new nodes.'
                );
        }
    }

    protected function saveWithParent(): void
    {
        $this->operation = Base::OPERATION_APPEND_TO;
        $this->node      = $this->parentWithTrashed;
    }

    protected function saveWithOutTargets(): void
    {
        $this->operation = Base::OPERATION_MAKE_ROOT;
        unset($this->attributes['_setRoot']);
    }

    /**
     * @throws TreeNeedValueException
     */
    protected function validateAndSetTreeID(): void
    {
        if (!$this->isMultiTree() || $this->treeValue() !== null) {
            return;
        }

        if ($this->treeAttribute()->isAutoGenerate()) {
            $this->setTree($this->getTreeConfig()->generateTreeId($this));

            return;
        }

        throw new TreeNeedValueException();
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
        $this->setAttribute($this->treeAttribute()->name(), $treeId);

        return $this;
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
        $this->setAttribute($this->leftAttribute()->name(), $to);
        $this->setAttribute($this->rightAttribute()->name(), ($to + 1));
        $this->setAttribute($this->levelAttribute()->name(), ($this->node->levelValue() + $depth));

        if ($this->isMultiTree() || ($depth > 0 && $this->node->isMultiTree())) {
            $this->setAttribute($this->treeAttribute()->name(), $this->node->treeValue());
        }

        $this->shift($to, null, 2);
    }

    /**
     * @return bool
     */
    public function isRoot(): bool
    {
        return $this->parentValue() === null;
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
                $tree = $this->treeValue();
            }

            foreach ([$this->leftAttribute()->name(), $this->rightAttribute()->name()] as $i => $attribute) {
                $query = $this->query();
                if ($this->isMultiTree()) {
                    $query->where($this->treeAttribute()->name(), $tree);
                }

                if ($to !== null) {
                    $query->whereBetween($attribute, [$from, $to]);
                } else {
                    $query->where($attribute, '>=', $from);
                }

                $query
                    ->when(static::isSoftDelete(), fn($query) => $query->withTrashed())
                    ->update(
                    [
                        $attribute => new Expression($attribute . '+ ' . $delta),
                    ]
                );
            }
        }
    }

    /**
     * clear all states
     */
    public function afterInsert(): void
    {
        $this->operation = null;
        $this->node      = null;
    }

    /**
     * @throws Exception
     */
    public function beforeUpdate(): void
    {
        $this->nodeRefresh();

        switch ($this->operation) {
            case Base::OPERATION_MAKE_ROOT:
                if (!$this->isMultiTree()) {
                    throw new Exception('Can not move a node as the root when Model is not set to "MultiTree"');
                }

                if ($this->getOriginal($this->treeAttribute()->name()) !== $this->treeValue()) {
                    $this->treeChange = $this->treeValue();
                    $this->setAttribute(
                        $this->treeAttribute()->name(),
                        $this->getOriginal($this->treeAttribute()->name())
                    );
                }
                break;

            case Base::OPERATION_INSERT_BEFORE:
            case Base::OPERATION_INSERT_AFTER:
                if (!$this->isMultiTree() && $this->node->isRoot()) {
                    throw new UniqueRootException(
                        $this->node,
                        'Can not move a node before/after root. Model must be "MultiTree"'
                    );
                }
            case Base::OPERATION_PREPEND_TO:
            case Base::OPERATION_APPEND_TO:
                if ($this->equalTo($this->node)) {
                    throw new Exception('Can not move a node when the target node is same.');
                }
                if ($this->node->isChildOf($this)) {
                    throw new Exception('Can not move a node when the target node is child.');
                }
        }
    }

    /**
     * @param Model|NestedSetTrait $model
     *
     * @return bool
     */
    public function equalTo(Model $model): bool
    {
        return
            $this->leftOffset() === $model->leftOffset() &&
            $this->rightOffset() === $model->rightOffset() &&
            $this->levelValue() === $model->levelValue() &&
            $this->parentValue() === $model->parentValue() &&
            $this->treeValue() === $model->treeValue()/*($this->isMultiTree()
                ? $this->treeValue() === $model->treeValue()
                : true
            )*/ ;
    }

    /**
     * @param Model|self $node
     *
     * @return bool
     */
    public function isChildOf(Model $node): bool
    {
        //        return ($this->isMultiTree() ? $this->treeValue() === $node->treeValue() : true) &&
        return $this->treeValue() === $node->treeValue() &&
            $this->leftOffset() > $node->leftOffset() &&
            $this->rightOffset() < $node->rightOffset();
    }

    /**
     * @throws TreeNeedValueException
     */
    public function afterUpdate(): void
    {
        switch ($this->operation) {
            case Base::OPERATION_MAKE_ROOT:
                if ($this->treeChange || $this->exists || !$this->isRoot()) {
                    $this->moveNodeAsRoot();
                }
                break;
            case Base::OPERATION_PREPEND_TO:
                $this->moveNode(($this->node->leftOffset() + 1), 1);
                break;
            case Base::OPERATION_APPEND_TO:
                $this->moveNode($this->node->rightOffset(), 1);
                break;
            case Base::OPERATION_INSERT_BEFORE:
                $this->moveNode($this->node->leftOffset());
                break;
            case Base::OPERATION_INSERT_AFTER:
                $this->moveNode($this->node->rightOffset() + 1);
                break;
        }

        $this->operation  = null;
        $this->node       = null;
        $this->treeChange = null;

        if ($this->forceSave) {
            $this->forceSave = false;
        }
    }

    /**
     * @throws TreeNeedValueException
     */
    protected function moveNodeAsRoot(): void
    {
        $left  = $this->leftOffset();
        $right = $this->rightOffset();
        $depth = $this->levelValue();


        if (!$this->treeAttribute()->isAutogenerate()) {
            throw new TreeNeedValueException();
        }

        $tree = $this->treeChange ?: $this->getTreeConfig()->generateTreeId($this);

        $this->newQueryWithTrashed()
            ->descendants(null, true)
            ->update(
                [
                    $this->leftAttribute()->name()  => new Expression(
                        $this->leftAttribute()->name() . ' + ' . (1 - $left)
                    ),
                    $this->rightAttribute()->name() => new Expression(
                        $this->rightAttribute()->name() . ' + ' . (1 - $left)
                    ),
                    $this->levelAttribute()->name() => new Expression(
                        $this->levelAttribute()->name() . ' + ' . -$depth
                    ),
                    $this->treeAttribute()->name()  => $tree,
                ]
            );

        $this->shift(($right + 1), null, ($left - $right - 1));
    }

    /**
     * @param int $to Left attribute
     * @param int $depth
     */
    protected function moveNode($to, $depth = 0): void
    {
        $left  = $this->leftOffset();
        $right = $this->rightOffset();
        $depth = ($this->levelValue() - $this->node->levelValue() - $depth);

        if (!$this->isMultiTree() || $this->treeValue() === $this->node->treeValue()) {
            // same root
            $this->newQueryWithTrashed()
                ->descendants(null, true)
                ->update(
                    [
                        $this->levelAttribute()->name() => new Expression(
                            "-{$this->levelAttribute()->name()} + " . $depth
                        ),
                    ]
                );

            $delta = ($right - $left + 1);

            if ($left >= $to) {
                $this->shift($to, ($left - 1), $delta);
                $delta = ($to - $left);
            } else {
                $this->shift(($right + 1), ($to - 1), -$delta);
                $delta = ($to - $right - 1);
            }

            $this->newQueryWithTrashed()
                ->descendants(null, true)
                ->where($this->levelAttribute()->name(), '<', 0)
                ->update(
                    [
                        $this->leftAttribute()->name()  => new Expression(
                            $this->leftAttribute()->name() . ' + ' . $delta
                        ),
                        $this->rightAttribute()->name() => new Expression(
                            $this->rightAttribute()->name() . ' + ' . $delta
                        ),
                        $this->levelAttribute()->name() => new Expression("-{$this->levelAttribute()->name()}"),
                    ]
                );
        } else {
            // move from other root
            $tree = $this->node->treeValue();
            $this->shift($to, null, ($right - $left + 1), $tree);
            $delta = ($to - $left);

            $this->newQueryWithTrashed()
                ->descendants(null, true)
                ->update(
                    [
                        $this->leftAttribute()->name()  => new Expression(
                            $this->leftAttribute()->name() . ' + ' . $delta
                        ),
                        $this->rightAttribute()->name() => new Expression(
                            $this->rightAttribute()->name() . ' + ' . $delta
                        ),
                        $this->levelAttribute()->name() => new Expression(
                            $this->levelAttribute()->name() . ' + ' . -$depth
                        ),
                        $this->treeAttribute()->name()  => $tree,
                    ]
                );

            $this->shift(($right + 1), null, ($left - $right - 1));
        }
    }

    public function beforeSave(): void
    {
        switch ($this->operation) {
            case Base::OPERATION_PREPEND_TO:
            case Base::OPERATION_APPEND_TO:
                $this->setAttribute($this->parentAttribute()->name(), $this->node->getKey());
                break;
            case Base::OPERATION_INSERT_AFTER:
            case Base::OPERATION_INSERT_BEFORE:
                $this->setAttribute($this->parentAttribute()->name(), $this->node->parentValue());
                break;
        }
    }

    /**
     * @throws DeleteRootException
     * @throws DeletedNodeHasChildrenException
     */
    public function beforeDelete(): void
    {
        if ($this->operation !== Base::OPERATION_DELETE_ALL && $this->isRoot()) {
            $this->onDeletedRootNode();
        }

        if (!static::isSoftDelete() && $this->children()->count() > 0) {
            $this->onDeletedNodeHasChildren();
        }

        $this->refresh();
    }

    /**
     * Callback on deleting root-node
     */
    protected function onDeletedRootNode(): void
    {
        if ($this->children()->count() > 0) {
            throw DeletedNodeHasChildrenException::make($this);
        }

        if (!$this->isMultiTree()) {
            throw DeleteRootException::make($this);
        }
    }


    /**
     * Callback on deleting node which has children
     *
     */
    protected function onDeletedNodeHasChildren(): void
    {
        //throw DeletedNodeHasChildrenException::make($this);
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
            ->hasMany($this::class, $this->parentAttribute()->name())
            ->setModel($this);
    }

    /**
     * Logic for build query
     */
    /*protected function onDeleteQueryForChildren()
    {
        return $this->children(); // $this->newNestedSetQuery()->descendants();
    }*/

    /**
     * If deleted node has children - these will be moved to parent of deleted node
     */
    public function afterDelete(): void
    {
        $left  = $this->leftOffset();
        $right = $this->rightOffset();

        if ($this->operation === Base::OPERATION_DELETE_ALL || $this->isLeaf()) {
            $this->shift(($right + 1), null, ($left - $right - 1));
        } else {
            $this->onDeleteNodeWeShouldToDeleteChildrenBy();

            $this->shift(($right + 1), null, -2);
        }

        $this->operation = null;
        $this->node      = null;
    }

    /**
     * Is leaf Node
     *
     * @return bool
     */
    public function isLeaf(): bool
    {
        if (self::isSoftDelete()) {
            return $this->children()->count() === 0;
        }

        return ($this->rightOffset() - $this->leftOffset()) === 1;
    }

    /**
     * @param Model $node
     *
     * @return $this
     */
    public function prependTo(Model $node): self
    {
        $this->operation = Base::OPERATION_PREPEND_TO;
        $this->node      = $node;

        return $this;
    }

    /**
     * @param Model $node
     *
     * @return $this
     */
    public function appendTo($node): self
    {
        $this->operation = Base::OPERATION_APPEND_TO;
        $this->node      = $node;

        return $this;
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
     * @param Model $node
     *
     * @return $this
     */
    public function insertBefore($node): self
    {
        $this->operation = Base::OPERATION_INSERT_BEFORE;
        $this->node      = $node;

        return $this;
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
     * @param Model $node
     *
     * @return $this
     */
    public function insertAfter($node): self
    {
        $this->operation = Base::OPERATION_INSERT_AFTER;
        $this->node      = $node;

        return $this;
    }

    /**
     * Relation to the parent.
     *
     * @return BelongsTo
     */
    public function parent(): BelongsTo
    {
        return $this
            ->belongsTo(get_class($this), $this->parentAttribute()->name())
            ->setModel($this);
    }


    /**
     * Relation to the parent.
     *
     * @return BelongsTo
     */
    public function parentWithTrashed(): BelongsTo
    {
        $query = $this->parent();

        if (static::isSoftDelete()) {
            $query->withTrashed();
        }

        return $query;
    }

    /**
     * Return parent by level
     *
     * @param int $level
     *
     * @return $this|null
     */
    public function parentByLevel(int $level): ?self
    {
        return $this->parents($level)->first();
    }

    /**
     * @param int $level
     *
     * @return bool
     */
    public function isLevel(int $level): bool
    {
        return $this->levelValue() === $level;
    }

    /**
     * @param int|null $level
     *
     * @return QueryBuilder[]|Collection
     */
    public function parents(?int $level = null)
    {
        return $this->parentsBuilder($level)->get();
    }

    /**
     * @param ?int $level
     *
     * @return QueryBuilder
     */
    public function parentsBuilder(?int $level = null)
    {
        return $this
            ->newQuery()
            ->parents($level);
    }

    /**
     * Get query ancestors of the node.
     *
     * @return  AncestorsRelation
     */
    public function ancestors(): AncestorsRelation
    {
        return new AncestorsRelation($this->newQuery(), $this);
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

    protected static ?Closure $customDeleteWithChildrenFn = null;

    public static function setCustomDeleteWithChildrenFn(callable $fn): void
    {
        static::$customDeleteWithChildrenFn = $fn;
    }

    protected static function getCustomDeleteWithChildrenFn($model, $forceDelete): mixed
    {
        return (
            static::$customDeleteWithChildrenFn ??
            static fn($model, $forceDelete) => $model->newQuery()
                ->descendants(null, true)
                ->when(
                    $forceDelete,
                    static fn($query) => $query->forceDelete(),
                    static fn($query) => $query->delete(),
                )
        )(
            $model,
            $forceDelete
        );
    }

    /**
     * @param bool $forceDelete
     *
     * @return mixed
     */
    public function deleteWithChildren(bool $forceDelete = true): mixed
    {
        $this->operation = Base::OPERATION_DELETE_ALL;

        if ($this->fireModelEvent('deleting') === false) {
            return false;
        }

        $result = static::getCustomDeleteWithChildrenFn($this, $forceDelete);

        $this->fireModelEvent('deleted', false);

        return $result;
    }

    /**
     * Move target node's children to it's parent
     */
    public function moveChildrenToParent(): void
    {
        $query = $this->children();

        $query
            ->when(static::isSoftDelete(), fn($query) => $query->withTrashed())
            ->update(
            [
                $this->leftAttribute()->name()   => new Expression($this->leftAttribute()->name() . '- 1'),
                $this->rightAttribute()->name()  => new Expression($this->rightAttribute()->name() . '- 1'),
                $this->levelAttribute()->name()  => new Expression($this->levelAttribute()->name() . '- 1'),
                $this->parentAttribute()->name() => $this->parentValue(),
            ]
        );
    }

    /**
     * Remove target node's children
     */
    public function removeDescendants(): void
    {
        $query = $this->newNestedSetQuery()->descendants();

        $query->delete();
    }

    protected function onDeleteNodeWeShouldToDeleteChildrenBy(): void
    {
        $this->moveChildrenToParent();
    }

    protected static ?Closure $customRestoreWithDescendantsFn = null;
    protected static ?Closure $customRestoreWithParentsFn     = null;

    /**
     * @param callable(Model, ?string): string|int|null $fn
     */
    public static function setCustomRestoreWithDescendantsFn(callable $fn): void
    {
        static::$customRestoreWithDescendantsFn = $fn;
    }

    protected static function getCustomRestoreWithDescendantsFn(Model $model, ?string $deletedAt = null): mixed
    {
        if ($fn = static::$customRestoreWithDescendantsFn) {
            return $fn($model, $deletedAt);
        }

        return static::restoreDescendants($model, $deletedAt);
    }

    /**
     * @param callable(Model, ?string): string|int|null $fn
     */
    public static function setCustomRestoreWithParentsFn(callable $fn): void
    {
        static::$customRestoreWithParentsFn = $fn;
    }


    protected static function getCustomRestoreWithParentsFn(Model $model, ?string $deletedAt = null): mixed
    {
        if ($fn = static::$customRestoreWithParentsFn) {
            return $fn($model, $deletedAt);
        }

        return static::restoreParents($model, $deletedAt);
    }

    protected function onRestoredNodeWeShouldToRestoredChildrenBy(): mixed
    {
        return static::getCustomRestoreWithDescendantsFn($this, static::$deletedAt);
    }

    /**
     * @return bool
     */
    public function saveAsRoot(): bool
    {
        if ($this->exists && $this->isRoot()) {
            return $this->save();
        }

        return $this->makeRoot()->save();
    }

    /**
     * @param int|string|null $tree
     *
     * @return $this
     */
    public function makeRoot(int|string|null $tree = null): self
    {
        $this->operation = Base::OPERATION_MAKE_ROOT;

        if ($tree) {
            $this->setTree($tree);
        }

        return $this;
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

    public function restoreWithParents(): mixed
    {
        if ($this->fireModelEvent('restoring') === false) {
            return false;
        }

        $result = static::getCustomRestoreWithParentsFn($this, self::$deletedAt);

        $this->exists = true;

        $this->fireModelEvent('restored', false);

        return $result;
    }

    /**
     * Restore the descendants.
     *
     * @param Model $model
     * @param       $deletedAt
     *
     * @return string|int|null
     */
    protected static function restoreParents(Model $model, $deletedAt): string|int|null
    {
        $query = $model->newNestedSetQuery()
            ->parents(null, true);

        if ($deletedAt) {
            $query->where($model->getDeletedAtColumn(), '>=', $deletedAt);
        }

        $result = $query->restore();

        return $result ? $model->getKey() : null;
    }

    public function restoreWithDescendants(): mixed
    {
        if ($this->fireModelEvent('restoring') === false) {
            return false;
        }

        $result = static::getCustomRestoreWithDescendantsFn($this, self::$deletedAt);

        $this->exists = true;

        $this->fireModelEvent('restored', false);

        return $result;
    }

    /**
     * Restore the descendants.
     *
     * @param Model|static|SoftDeletes $model
     * @param $deletedAt
     *
     * @return mixed
     */
    protected static function restoreDescendants(Model $model, $deletedAt): string|int|null
    {
        $query = $model->newNestedSetQuery()
            ->descendants(null, true);

        if ($deletedAt) {
            $query->where($model->getDeletedAtColumn(), '>=', $deletedAt);
        }

        $result = $query->restore();

        return $result ? $model->getKey() : null;
    }

    public function afterRestore(): void
    {
        // $this->onRestoredNodeWeShouldToRestoredChildrenBy();

        $this->operation  = null;
        $this->node       = null;
        $this->treeChange = null;

        if ($this->forceSave) {
            $this->forceSave = false;
        }
    }

    public function beforeRestore(): void
    {
        $this->operation = Base::OPERATION_RESTORE_SELF_ONLY;
        //        static::$deletedAt = $this->{$this->getDeletedAtColumn()};
    }
}
