<?php

namespace Fureev\Trees;

use Fureev\Trees\Exceptions\{DeleteRootException, Exception, UniqueRootException, UnsavedNodeException};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use Php\Support\Exceptions\NotSupportedException;

/**
 * Trait NestedSetTrait
 *
 * @package Fureev\Trees
 * @property Model $parent
 * @property Collection|Model[] $children
 * // * @property Collection|Model[] $siblings
 * @method QueryBuilder newQuery()
 * @method QueryBuilder query()
 * @mixin Model
 */
trait NestedSetTrait
{
    use BaseNestedSetTrait;

    /**
     * @return $this
     */
    public function makeRoot(): self
    {
        $this->operation = NestedSetConfig::OPERATION_MAKE_ROOT;

        return $this;
    }

    /**
     * @param Model $node
     *
     * @return $this
     */
    public function prependTo(Model $node): self
    {
        $this->operation = NestedSetConfig::OPERATION_PREPEND_TO;
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
        $this->operation = NestedSetConfig::OPERATION_APPEND_TO;
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
        $this->operation = NestedSetConfig::OPERATION_INSERT_BEFORE;
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
        $this->operation = NestedSetConfig::OPERATION_INSERT_AFTER;
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
     * @return \Fureev\Trees\QueryBuilder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public function getRoot()
    {
        return $this->newQuery()->root()->first();
    }

    /**
     * @throws \Exception
     */
    public function beforeInsert(): void
    {
        if ($this->node !== null && $this->node->exists) {
            $this->node->refresh();
        }

        if (!$this->operation && $this->getAttributeFromArray('_setRoot')) {
            $this->operation = NestedSetConfig::OPERATION_MAKE_ROOT;
            unset($this->attributes['_setRoot']);
        }

        switch ($this->operation) {
            case NestedSetConfig::OPERATION_MAKE_ROOT:
                $condition = [$this->getLeftAttributeName() => 1];

                if (($exist = $this->where($condition)->first()) !== null) {
                    throw new UniqueRootException($exist);
                }
                $this->setAttribute($this->getLeftAttributeName(), 1);
                $this->setAttribute($this->getRightAttributeName(), 2);
                $this->setAttribute($this->getLevelAttributeName(), 0);
//                $this->setAttribute($this->getParentIdName(), null);
                break;
            case NestedSetConfig::OPERATION_PREPEND_TO:
                $this->validateExisted();
//                $this->setAttribute($this->getParentIdName(), $this->node->getKey());
                $this->insertNode($this->node->getLeftOffset() + 1, 1);
                break;
            case NestedSetConfig::OPERATION_APPEND_TO:
                $this->validateExisted();
//                $this->setAttribute($this->getParentIdName(), $this->node->getKey());
                $this->insertNode($this->node->getRightOffset(), 1);
                break;
            case NestedSetConfig::OPERATION_INSERT_BEFORE:
                $this->validateExisted();
//                $this->setAttribute($this->getParentIdName(), $this->node->getParentId());
                $this->insertNode($this->node->getLeftOffset());
                break;
            case NestedSetConfig::OPERATION_INSERT_AFTER:
                $this->validateExisted();
//                $this->setAttribute($this->getParentIdName(), $this->node->getParentId());
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
     * @throws \Fureev\Trees\Exceptions\DeleteRootException
     * @throws \Fureev\Trees\Exceptions\UnsavedNodeException
     */
    public function beforeDelete(): void
    {
        if ($this->operation !== NestedSetConfig::OPERATION_DELETE_ALL && $this->isRoot()) {
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

        if ($this->operation === NestedSetConfig::OPERATION_DELETE_ALL || $this->isLeaf()) {
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
        if ($this->node !== null && $this->node->exists) {
            $this->node->refresh();
        }

        switch ($this->operation) {
            case NestedSetConfig::OPERATION_INSERT_BEFORE:
            case NestedSetConfig::OPERATION_INSERT_AFTER:
                if ($this->node->isRoot()) {
                    throw new UniqueRootException($this->node, 'Can not move a node before/after root.');
                }
            case NestedSetConfig::OPERATION_PREPEND_TO:
            case NestedSetConfig::OPERATION_APPEND_TO:
                /*if (!$this->node->exists) {
                    throw new Exception('Can not move a node when the target node is new record.');
                }*/
                if ($this->equalTo($this->node)) {
                    throw new Exception('Can not move a node when the target node is same.');
                }
                if ($this->node->isChildOf($this)) {
                    throw new Exception('Can not move a node when the target node is child.');
                }
        }
    }

    /**
     *
     */
    public function afterUpdate(): void
    {
        switch ($this->operation) {
            /*case NestedSetConfig::OPERATION_MAKE_ROOT:
                if (!$this->isRoot() || !$this->exist) {
//                    $this->moveNodeAsRoot();
                }
                break;*/
            case NestedSetConfig::OPERATION_PREPEND_TO:
                $this->moveNode($this->node->getLeftOffset() + 1, 1);
                break;
            case NestedSetConfig::OPERATION_APPEND_TO:
                $this->moveNode($this->node->getRightOffset(), 1);
                break;
            case NestedSetConfig::OPERATION_INSERT_BEFORE:
                $this->moveNode($this->node->getLeftOffset());
                break;
            case NestedSetConfig::OPERATION_INSERT_AFTER:
                $this->moveNode($this->node->getRightOffset() + 1);
                break;
        }

        $this->operation = null;
        $this->node = null;
    }


    public function beforeSave(): void
    {
        switch ($this->operation) {
            case NestedSetConfig::OPERATION_PREPEND_TO:
            case NestedSetConfig::OPERATION_APPEND_TO:
                $this->setAttribute($this->getParentIdName(), $this->node->getKey());
                break;
            case NestedSetConfig::OPERATION_INSERT_AFTER:
            case NestedSetConfig::OPERATION_INSERT_BEFORE:
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
        static::creating(function ($model) {
            /** @var NestedSetTrait $model */
            return $model->beforeInsert();
        });

        static::created(function ($model) {
            /** @var NestedSetTrait $model */
            return $model->afterInsert();
        });

        static::updating(function ($model) {
            /** @var NestedSetTrait $model */
            return $model->beforeUpdate();
        });

        static::updated(function ($model) {
            /** @var NestedSetTrait $model */
            return $model->afterUpdate();
        });

        static::saving(function ($model) {
            /** @var NestedSetTrait $model */
            return $model->beforeSave();
        });

        static::deleting(function ($model) {
            // We will need fresh data to delete node safely
            /** @var NestedSetTrait $model */
            $model->beforeDelete();
        });

        static::deleted(function ($model) {
            /** @var NestedSetTrait $model */
            $model->afterDelete();
        });

        if (static::isSoftDelete()) {
            static::restoring(function ($model) {
                static::$deletedAt = $model->{$model->getDeletedAtColumn()};
            });

            static::restored(function ($model) {
                $model->restoreDescendants(static::$deletedAt);
//                $model->refresh();
//                $model->appendTo($model->parent)->save();
            });
        }
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model|self $node
     *
     * @return bool
     */
    public function isChildOf(Model $node): bool
    {
        return $this->getLeftOffset() > $node->getLeftOffset()
            && $this->getRightOffset() < $node->getRightOffset();
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
     * @param \Illuminate\Database\Eloquent\Model|\Fureev\Trees\NestedSetTrait $model
     *
     * @return bool
     */
    public function equalTo(Model $model): bool
    {
        return
            $this->getLeftOffset() === $model->getLeftOffset() &&
            $this->getRightOffset() === $model->getRightOffset() &&
            $this->getLevel() === $model->getLevel() &&
            $this->getParentId() === $model->getParentId();
    }


    /**
     * Relation to the parent.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo(get_class($this), $this->getParentIdName())
            ->setModel($this);
    }

    /**
     * @param int|null $level
     *
     * @return \Fureev\Trees\QueryBuilder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function parents($level = null)
    {
        return $this
            ->newQuery()
            ->parents($level)
            ->get();
    }

    /**
     * Relation to children.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function children()
    {
        return $this
            ->hasMany(get_class($this), $this->getParentIdName())
            ->setModel($this);
    }

    /**
     * @return false|int
     */
    public function deleteWithChildren()
    {
        $this->operation = NestedSetConfig::OPERATION_DELETE_ALL;

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
    protected function restoreDescendants($deletedAt)
    {
        $this->newNestedSetQuery()
            ->descendants(null, true)
            ->where($this->getDeletedAtColumn(), '>=', $deletedAt)
            ->restore();
    }

    /**
     * @param int $to
     * @param int $depth
     *
     * @throws \Fureev\Trees\Exceptions\Exception
     */
    protected function insertNode($to, $depth = 0): void
    {
        if ($depth === 0 && $this->node->isRoot()) {
            throw new UniqueRootException($this->node, 'Can not insert a node before/after root.');
        }
        $this->setAttribute($this->getLeftAttributeName(), $to);
        $this->setAttribute($this->getRightAttributeName(), $to + 1);
        $this->setAttribute($this->getLevelAttributeName(), $this->node->getLevel() + $depth);

        $this->shift($to, null, 2);
    }

    /**
     * @param $to
     * @param int $depth
     */
    protected function moveNode($to, $depth = 0): void
    {
        $left = $this->getLeftOffset();
        $right = $this->getRightOffset();
        $depth = $this->getLevel() - $this->node->getLevel() - $depth;

        // same root
        $query = $this->newQuery()->descendants(null, true);


        $query->update([
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

        $query = $this->newQuery()
            ->descendants(null, true)
            ->where($this->getLevelAttributeName(), '<', 0);

        $query->update([
            $this->getLeftAttributeName() => new Expression($this->getLeftAttributeName() . ' + ' . $delta),
            $this->getRightAttributeName() => new Expression($this->getRightAttributeName() . ' + ' . $delta),
            $this->getLevelAttributeName() => new Expression("-{$this->getLevelAttributeName()}"),
        ]);

    }

    /**
     * @param $from
     * @param $to
     * @param $delta
     */
    protected function shift($from, $to, $delta): void
    {
        if ($delta !== 0 && ($to === null || $to >= $from)) {
            foreach ([$this->getLeftAttributeName(), $this->getRightAttributeName()] as $i => $attribute) {

                $query = $this->query();

                if ($to !== null) {
                    $query->whereBetween($attribute, [$from, $to]);
                } else {
                    $query->where($attribute, '>=', $from);
                }

                $query->update([
                    $attribute => new Expression($attribute . '+ ' . $delta)
                ]);
            }
        }
    }

}
