<?php

declare(strict_types=1);

namespace Fureev\Trees;

use Closure;
use Fureev\Trees\Config\Helper;
use Fureev\Trees\Config\Operation;
use Fureev\Trees\Exceptions\DeletedNodeHasChildrenException;
use Fureev\Trees\Exceptions\DeleteRootException;
use Fureev\Trees\Exceptions\Exception;
use Fureev\Trees\Exceptions\NotSupportedException;
use Fureev\Trees\Exceptions\TreeNeedValueException;
use Fureev\Trees\Exceptions\UniqueRootException;
use Fureev\Trees\Generators\GeneratorTreeIdContract;
use Fureev\Trees\Generators\GeneratorTreeIdTreeId;
use Fureev\Trees\Strategy\ChildrenHandler;
use Fureev\Trees\Strategy\DeleteStrategy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Expression;

/**
 * @mixin QueryBuilderV2
 */
trait UseNestedSet
{
    use WithQueryBuilder;
    use WithRelations;

    /** @var Model|static|null */
    protected ?Model $node = null;

    protected int|string|null $treeChange = null;

    protected ?Operation $operation = null;

    protected bool $forceSave = false;

    public static function bootUseNestedSet(): void
    {
        static::creating(
            static function ($model) {
                /** @var static $model */
                $model->beforeInsert();
            }
        );

        static::created(
            static function ($model) {
                /** @var static $model */
                $model->afterInsert();
            }
        );

        static::updating(
            static function ($model) {
                /** @var static $model */
                $model->beforeUpdate();
            }
        );

        static::updated(
            static function ($model) {
                /** @var static $model */
                $model->afterUpdate();
            }
        );

        static::saving(
            static function ($model) {
                /** @var static $model */
                $model->beforeSave();
            }
        );

        static::deleting(
            static function ($model) {
                /** @var static $model */
                $model->beforeDelete();
            }
        );

        static::deleted(
            static function ($model) {
                /** @var static $model */
                if ($model->isSoftDelete() && !$model->isForceDeleting()) {
                    return;
                }

                $model->afterDelete();
            }
        );

        if (Helper::isModelSoftDeletable(static::class)) {
            static::restoring(
                static function (Model $model) {
                    /** @var static $model */
                    $model->beforeRestore();
                }
            );

            static::restored(
                static function (Model $model) {
                    /** @var static $model */
                    $model->afterRestore();
                }
            );
        }
    }

    public function beforeInsert(): void
    {
        $this->nodeRefresh();

        if (!$this->operation) {
            if ($parent = $this->parentWithTrashed) {
                $this->markWithParent($parent);
            } else {
                if ($this->isMulti() || $this->getAttributeFromArray('_setRoot')) {
                    $this->markAsRoot();
                }
            }
        }

        switch ($this->operation) {
            case Operation::MakeRoot:
                if (!$this->isMulti() && ($exist = $this->root()->first()) !== null) {
                    throw new UniqueRootException($exist);
                }

                $this->validateAndSetTreeId();

                $this->setAttribute((string)$this->leftAttribute(), 1);
                $this->setAttribute((string)$this->rightAttribute(), 2);
                $this->setAttribute((string)$this->levelAttribute(), 0);

                break;

            case Operation::PrependTo:
                $this->validateExisted();
                $this->insertNode(($this->node->leftValue() + 1), 1);
                break;
            case Operation::AppendTo:
                $this->validateExisted();
                $this->insertNode($this->node->rightValue(), 1);
                break;
            case Operation::InsertBefore:
                $this->validateExisted();
                $this->insertNode($this->node->leftValue());
                break;
            case Operation::InsertAfter:
                $this->validateExisted();
                $this->insertNode($this->node->rightValue() + 1);
                break;
            default:
                throw new NotSupportedException(
                    null,
                    sprintf('Method "%s::insert" is not supported for inserting new nodes.', $this::class)
                );
        }
    }

    public function beforeUpdate(): void
    {
        $this->nodeRefresh();

        switch ($this->operation) {
            case Operation::MakeRoot:
                if (!$this->isMulti()) {
                    throw new Exception('Can not move a node as the root when Model is not set to "MultiTree"');
                }

                if ($this->getOriginal((string)$this->treeAttribute()) !== ($newTreeValue = $this->treeValue())) {
                    $this->treeChange = $newTreeValue;
                    $this->setAttribute(
                        (string)$this->treeAttribute(),
                        $this->getOriginal((string)$this->treeAttribute())
                    );
                }
                break;

            case Operation::InsertBefore:
            case Operation::InsertAfter:
                if (!$this->isMulti() && $this->node->isRoot()) {
                    throw new UniqueRootException(
                        $this->node,
                        'Can not move a node before/after root. Model must be "MultiTree"'
                    );
                }
            // todo: break; ??

            case Operation::PrependTo:
            case Operation::AppendTo:
                if ($this->isEqualTo($this->node)) {
                    throw new Exception('Can not move a node when the target node is same.');
                }

                if ($this->node->isChildOf($this)) {
                    throw new Exception('Can not move a node when the target node is child.');
                }
            // todo: break; ??
        }
    }

    public function beforeSave(): void
    {
        switch ($this->operation) {
            case Operation::PrependTo:
            case Operation::AppendTo:
                $this->setAttribute((string)$this->parentAttribute(), $this->node->getKey());
                break;
            case Operation::InsertBefore:
            case Operation::InsertAfter:
                $this->setAttribute((string)$this->parentAttribute(), $this->node->parentValue());
                break;
            default:
                break;
        }
    }

    public function afterInsert(): void
    {
        $this->operation = null;
        $this->node      = null;
    }

    public function afterUpdate(): void
    {
        switch ($this->operation) {
            case Operation::MakeRoot:
                if ($this->treeChange || $this->exists || !$this->isRoot()) {
                    $this->moveNodeAsRoot();
                }
                break;
            case Operation::PrependTo:
                $this->moveNode(($this->node->leftValue() + 1), 1);
                break;
            case Operation::AppendTo:
                $this->moveNode($this->node->rightValue(), 1);
                break;
            case Operation::InsertBefore:
                $this->moveNode($this->node->leftValue());
                break;
            case Operation::InsertAfter:
                $this->moveNode($this->node->rightValue() + 1);
                break;
        }

        $this->operation  = null;
        $this->node       = null;
        $this->treeChange = null;
        $this->forceSave  = false;
    }


    public function beforeDelete(): void
    {
        if ($this->operation !== Operation::DeleteAll && $this->isRoot()) {
            $this->onDeletingRootNode();
        }

        if (!$this->isSoftDelete() && $this->children()->count() > 0) {
            $this->onDeletingNodeHasChildren();
        }

        // We will need fresh data to delete node safely
        $this->refresh();
    }

    /**
     * If deleted node has children - these will be moved children to parent node of deleted node
     */
    public function afterDelete(): void
    {
        $left  = $this->leftValue();
        $right = $this->rightValue();

        if ($this->operation === Operation::DeleteAll || $this->isLeaf()) {
            $this->shift(($right + 1), null, ($left - $right - 1));
        } else {
            $handler = static::resolveChildrenHandler($this->getTreeConfig()->childrenHandlerOnDelete);
            $handler->handle($this);

            $this->shift(($right + 1), null, -2);
        }

        $this->operation = null;
        $this->node      = null;
    }

    protected function onDeletingRootNode(): void
    {
        if ($this->children()->count() > 0) {
            throw new DeletedNodeHasChildrenException($this);
        }

        if (!$this->isMulti()) {
            throw new DeleteRootException($this);
        }
    }


    /**
     * Callback on deleting node which has children
     */
    protected function onDeletingNodeHasChildren(): void
    {
        //throw DeletedNodeHasChildrenException::make($this);
    }

    protected static function resolveDeleterWithChildren(string $value): DeleteStrategy
    {
        $remover = instance($value);
        if (!$remover instanceof DeleteStrategy) {
            throw new Exception('Invalid Delete Strategy for `deleteWithChildren`');
        }

        return $remover;
    }

    protected static function resolveChildrenHandler(string $value): ChildrenHandler
    {
        $remover = instance($value);
        if (!$remover instanceof ChildrenHandler) {
            throw new Exception('Invalid ChildrenHandler for `delete`');
        }

        return $remover;
    }

    public function deleteWithChildren(bool $forceDelete = true): mixed
    {
        $this->operation = Operation::DeleteAll;

        if ($this->fireModelEvent('deleting') === false) {
            return false;
        }

        if ($this->isSoftDelete()) {
            $this->forceDeleting = $forceDelete;
        }

        $remover = static::resolveDeleterWithChildren($this->getTreeConfig()->deleterWithChildren);
        $result  = $remover->handle($this, $forceDelete);

        $this->fireModelEvent('deleted', false);

        return $result;
    }

    /**
     * Remove target node's children
     */
    public function removeDescendants(): void
    {
        $this->newNestedSetQuery()->descendantsQuery()->delete();
    }

    public function isMulti(): bool
    {
        if ($this->node !== null) {
            return $this->node->getTreeConfig()->isMulti();
        }

        return $this->getTreeConfig()->isMulti();
    }

    //    public function makeRoot(int|string|null $tree = null): self
    public function makeRoot(): static
    {
        $this->operation = Operation::MakeRoot;

        //        if ($tree) {
        //                    $this->setTree($tree);
        //        }

        return $this;
    }

    public function saveAsRoot(): bool
    {
        if ($this->exists && $this->isRoot()) {
            return $this->save();
        }

        return $this->makeRoot()->save();
    }

    public function up(): bool
    {
        $prev = $this->prevSibling()->first();
        if (!$prev) {
            return false;
        }

        return $this->insertBefore($prev)->forceSave();
    }

    public function down(): bool
    {
        $next = $this->nextSibling()->first();

        if (!$next) {
            return false;
        }

        return $this->insertAfter($next)->forceSave();
    }

    public function forceSave(): bool
    {
        $this->forceSave = true;

        return $this->save();
    }

    public function isForceSaving(): bool
    {
        return $this->forceSave;
    }

    public function getDirty(): array
    {
        $dirty = parent::getDirty();

        if (!$dirty && $this->forceSave) {
            $dirty[(string)$this->parentAttribute()] = $this->parentValue();
        }

        return $dirty;
    }

    protected function validateAndSetTreeId(): void
    {
        if (!$this->isMulti() || $this->treeValue() !== null) {
            return;
        }

        if ($this->treeIdGenerator() !== null) {
            $this->setTree($this->generateTreeId());

            return;
        }

        throw new TreeNeedValueException();
    }

    protected function treeIdGenerator(): ?string
    {
        return GeneratorTreeIdTreeId::class;
    }

    protected function generateTreeId(): string|int
    {
        $generator = instance($this->treeIdGenerator(), $this->treeAttribute());
        if ($generator instanceof GeneratorTreeIdContract) {
            return $generator->generateId($this);
        }

        throw new Exception('Invalid Generator');
    }

    public function setTree(string|int $treeId): static
    {
        if (!$this->isMulti()) {
            throw new Exception('Model does not implement MultiTree');
        }

        $this->setAttribute((string)$this->treeAttribute(), $treeId);

        return $this;
    }

    protected function nodeRefresh(): void
    {
        if ($this->node?->exists) {
            $this->node->refresh();
        }
    }

    protected function markAsRoot(): void
    {
        $this->operation = Operation::MakeRoot;
        unset($this->attributes['_setRoot']);
    }

    protected function markWithParent(Model $model): void
    {
        $this->operation = Operation::AppendTo;
        $this->node      = $model;
    }

    protected function validateExisted(): void
    {
        if (!$this->node->exists) {
            throw new Exception('Can not manipulate a node when the target node is a new record.');
        }
    }

    /**
     * Allows insert a new node before all children nodes in the target node
     *
     * @example `$model->prependTo($modelRoot)->save();`
     */
    public function prependTo(Model $node): static
    {
        $this->operation = Operation::PrependTo;
        $this->node      = $node;

        return $this;
    }

    /**
     * Allows insert a new node after all children nodes in the target node
     *
     * @example `$model->appendTo($modelRoot)->save();`
     */
    public function appendTo(Model $node): static
    {
        $this->operation = Operation::AppendTo;
        $this->node      = $node;

        return $this;
    }

    /**
     * Allows insert a new node before the target node (on the same level)
     */
    public function insertBefore(Model $node): static
    {
        $this->operation = Operation::InsertBefore;
        $this->node      = $node;

        return $this;
    }

    /**
     * Allows insert a new node after the target node (on the same level)
     */
    public function insertAfter(Model $node): static
    {
        $this->operation = Operation::InsertAfter;
        $this->node      = $node;

        return $this;
    }

    /**
     * @param int $to Left attribute
     *
     * @throws UniqueRootException
     */
    protected function insertNode(int $to, int $depth = 0): void
    {
        if ($depth === 0 && $this->node->isRoot()) {
            throw new UniqueRootException($this->node, 'Can not insert a node before/after root.');
        }

        $this->setAttribute((string)$this->leftAttribute(), $to);
        $this->setAttribute((string)$this->rightAttribute(), ($to + 1));
        $this->setAttribute((string)$this->levelAttribute(), ($this->node->levelValue() + $depth));

        if ($this->isMulti() || ($depth > 0 && $this->node->isMulti())) {
            $this->setAttribute((string)$this->treeAttribute(), $this->node->treeValue());
        }

        $this->shift($to, null, 2);
    }

    public function beforeRestore(): void
    {
        $this->operation = Operation::RestoreSelfOnly;
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

    /// *********************
    ///
    /// Restore block: start
    ///
    /// Todo: move to actions
    ///
    protected static ?Closure $customRestoreWithDescendantsFn = null;
    protected static ?Closure $customRestoreWithParentsFn     = null;

    public function restoreWithParents(?string $deletedAt = null): mixed
    {
        if ($this->fireModelEvent('restoring') === false) {
            return false;
        }

        $result = static::getCustomRestoreWithParentsFn($this, $deletedAt);

        $this->exists = true;

        $this->fireModelEvent('restored', false);

        return $result;
    }

    protected static function getCustomRestoreWithParentsFn(Model $model, ?string $deletedAt = null): mixed
    {
        if ($fn = static::$customRestoreWithParentsFn) {
            return $fn($model, $deletedAt);
        }

        return static::restoreParents($model, $deletedAt);
    }

    /**
     * Restore the descendants.
     *
     * @param Model|static|SoftDeletes $model
     */
    protected static function restoreParents(Model $model, ?string $deletedAt = null): string|int|null
    {
        $query = $model->newNestedSetQuery()
            ->parents(null, true);

        if ($deletedAt) {
            $query->where($model->getDeletedAtColumn(), '>=', $deletedAt);
        }

        $result = $query->restore();

        return $result ? $model->getKey() : null;
    }

    public function restoreWithDescendants(?string $deletedAt = null): mixed
    {
        if ($this->fireModelEvent('restoring') === false) {
            return false;
        }

        $result = static::getCustomRestoreWithDescendantsFn($this, $deletedAt);

        $this->exists = true;

        $this->fireModelEvent('restored', false);

        return $result;
    }

    protected static function getCustomRestoreWithDescendantsFn(Model $model, ?string $deletedAt = null): mixed
    {
        if ($fn = static::$customRestoreWithDescendantsFn) {
            return $fn($model, $deletedAt);
        }

        return static::restoreDescendants($model, $deletedAt);
    }

    /**
     * @param Model|static|SoftDeletes $model
     */
    protected static function restoreDescendants(Model $model, ?string $deletedAt = null): string|int|null
    {
        $query = $model->newNestedSetQuery()
            ->descendantsQuery(null, true);

        if ($deletedAt) {
            $query->where($model->getDeletedAtColumn(), '>=', $deletedAt);
        }

        $result = $query->restore();

        return $result ? $model->getKey() : null;
    }

    ///
    /// Restore block: finish
    ///
    /// *********************

    protected function shift(int $from, ?int $to, int $delta, int|string $tree = null): void
    {
        // todo: reformat: and test it
        // if ($delta === 0 || !($to === null || $to >= $from)) { return }
        if ($delta !== 0 && ($to === null || $to >= $from)) {
            if ($tree === null && $this->isMulti()) {
                $tree = $this->treeValue();
            }

            foreach ([(string)$this->leftAttribute(), (string)$this->rightAttribute()] as $i => $attribute) {
                $query = $this->query();
                if ($this->isMulti()) {
                    $query->where((string)$this->treeAttribute(), $tree);
                }

                if ($to !== null) {
                    $query->whereBetween($attribute, [$from, $to]);
                } else {
                    $query->where($attribute, '>=', $from);
                }

                if ($this->isSoftDelete()) {
                    $query->withTrashed();
                }

                $query->update(
                    [
                        $attribute => new Expression($attribute . '+ ' . $delta),
                    ]
                );
            }
        }
    }

    protected function moveNode(int $to, int $depth = 0): void
    {
        $left  = $this->leftValue();
        $right = $this->rightValue();
        $depth = ($this->levelValue() - $this->node->levelValue() - $depth);

        if (!$this->isMulti() || $this->treeValue() === $this->node->treeValue()) {
            // same root
            $this->newQuery()
                ->descendantsQuery(null, true)
                ->update(
                    [
                        (string)$this->levelAttribute() => new Expression(
                            "-{$this->levelAttribute()} + " . $depth
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

            $this->newQuery()
                ->descendantsQuery(null, true)
                ->where((string)$this->levelAttribute(), '<', 0)
                ->update(
                    [
                        (string)$this->leftAttribute()  => new Expression(
                            $this->leftAttribute() . ' + ' . $delta
                        ),
                        (string)$this->rightAttribute() => new Expression(
                            $this->rightAttribute() . ' + ' . $delta
                        ),
                        (string)$this->levelAttribute() => new Expression("-{$this->levelAttribute()}"),
                    ]
                );
        } else {
            // move from other root
            $tree = $this->node->treeValue();
            $this->shift($to, null, ($right - $left + 1), $tree);
            $delta = ($to - $left);

            $this->newQuery()
                ->descendantsQuery(null, true)
                ->update(
                    [
                        (string)$this->leftAttribute()  => new Expression(
                            $this->leftAttribute() . ' + ' . $delta
                        ),
                        (string)$this->rightAttribute() => new Expression(
                            $this->rightAttribute() . ' + ' . $delta
                        ),
                        (string)$this->levelAttribute() => new Expression(
                            $this->levelAttribute() . ' + ' . -$depth
                        ),
                        (string)$this->treeAttribute()  => $tree,
                    ]
                );

            $this->shift(($right + 1), null, ($left - $right - 1));
        }
    }

    protected function moveNodeAsRoot(): void
    {
        $left  = $this->leftValue();
        $right = $this->rightValue();
        $depth = $this->levelValue();

        if ($this->treeIdGenerator() === null) {
            throw new TreeNeedValueException();
        }

        $tree = $this->treeChange ?: $this->generateTreeId();

        $this->newQuery()
            ->descendantsQuery(null, true)
            ->update(
                [
                    (string)$this->leftAttribute()  => new Expression(
                        $this->leftAttribute() . ' + ' . (1 - $left)
                    ),
                    (string)$this->rightAttribute() => new Expression(
                        $this->rightAttribute() . ' + ' . (1 - $left)
                    ),
                    (string)$this->levelAttribute() => new Expression(
                        $this->levelAttribute() . ' + ' . -$depth
                    ),
                    (string)$this->treeAttribute()  => $tree,
                ]
            );

        $this->shift(($right + 1), null, ($left - $right - 1));
    }

    /**
     * Move target node's children to it's parent
     */
    public function moveChildrenToParent(): void
    {
        $this->descendantsQuery()
            ->update(
                [
                    (string)$this->leftAttribute()  => new Expression($this->leftAttribute() . '- 1'),
                    (string)$this->rightAttribute() => new Expression($this->rightAttribute() . '- 1'),
                    (string)$this->levelAttribute() => new Expression($this->levelAttribute() . '- 1'),
                ]
            );

        $parent = $this->parent;

        $condition = [
            [
                (string)$this->levelAttribute(),
                '=',
                ($parent->levelValue() + 1),
            ],
        ];

        $this
            ->where($condition)
            ->treeCondition()
            ->update(
                [
                    (string)$this->parentAttribute() => $parent->getKey(),
                ]
            );
    }

    public function trace(): array
    {
        return [
            'left'  => $this->leftValue(),
            'right' => $this->rightValue(),
            'level' => $this->levelValue(),
            'tech'  => [
                'forceSave' => $this->forceSave,
                'operation' => $this->operation,
                'node'      => $this->node?->getKey(),
            ],
        ];
    }
}
