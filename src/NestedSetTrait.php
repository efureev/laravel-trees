<?php

namespace Fureev\Trees;

use Fureev\Trees\Exceptions\Exception;
use Fureev\Trees\Exceptions\UniqueRootException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use Php\Support\Exceptions\NotSupportedException;

/**
 * Trait NestedSetTrait
 *
 * @package Fureev\Trees
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
    public function makeRoot()
    {
        $this->operation = NestedSetConfig::OPERATION_MAKE_ROOT;

        return $this;
    }

    /**
     * @param Model $node
     *
     * @return $this
     */
    public function prependTo(Model $node)
    {
        $this->operation = NestedSetConfig::OPERATION_PREPEND_TO;
        $this->node = $node;

        return $this;
    }


    /**
     * @return bool
     */
    public function isRoot(): bool
    {
        return is_null($this->getParentId());
    }

    /**
     * @return \Fureev\Trees\QueryBuilder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public function getRoot()
    {
        return $this->newQuery()->whereIsRoot()->first();
    }

    /**
     * @throws \Exception
     */
    public function beforeInsert()
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

                if (($exist = $this::where($condition)->first()) !== null) {
                    throw new UniqueRootException($exist);
                }
                $this->setAttribute($this->getLeftAttributeName(), 1);
                $this->setAttribute($this->getRightAttributeName(), 2);
                $this->setAttribute($this->getLevelAttributeName(), 0);
                $this->setAttribute($this->getParentIdName(), null);
                break;
            case NestedSetConfig::OPERATION_PREPEND_TO:
                $this->insertNode($this->node->getLeftOffset() + 1, 1);
                break;
            /*case self::OPERATION_APPEND_TO:
                $this->insertNode($this->node->getAttribute($this->rightAttribute), 1);
                break;
            case self::OPERATION_INSERT_BEFORE:
                $this->insertNode($this->node->getAttribute($this->leftAttribute), 0);
                break;
            case self::OPERATION_INSERT_AFTER:
                $this->insertNode($this->node->getAttribute($this->rightAttribute) + 1, 0);
                break;*/
            default:
                throw new NotSupportedException(null, 'Method "' . get_class($this) . '::insert" is not supported for inserting new nodes.');
        }
    }


    /**
     * Sign on model events.
     */
    public static function bootNestedSetTrait()
    {

        static::creating(function ($model) {
            return $model->beforeInsert();
        });

        /*static::saving(function ($model) {
            return $model->beforeInsert();
        });

        */
        /*static::updating(function ($model) {
            return $model->beforeInsert();
        });*/

        /*static::saving(function ($model) {
            return $model->callPendingAction();
        });*/
        /*  static::deleting(function ($model) {
              // We will need fresh data to delete node safely
              $model->refreshNode();
          });
          static::deleted(function ($model) {
              $model->deleteDescendants();
          });
          if (static::usesSoftDelete()) {
              static::restoring(function ($model) {
                  static::$deletedAt = $model->{$model->getDeletedAtColumn()};
              });
              static::restored(function ($model) {
                  $model->restoreDescendants(static::$deletedAt);
              });
          }*/
    }

    /**
     * Apply parent model.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return $this
     */
    public function setParent(Model $model)
    {
        $this->parent()->associate($model);

        /*
        $this->setParentId($value ? $value->getKey() : null)
            ->setRelation('parent', $value);*/

        return $this;
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
        return $this->hasMany(get_class($this), $this->getParentIdName())
            ->setModel($this);
    }

    /**
     * @param int $to
     * @param int $depth
     *
     * @throws \Fureev\Trees\Exceptions\Exception
     */
    protected function insertNode($to, $depth = 0)
    {
        if (!$this->node->exists()) {
            throw new Exception('Can not create a node when the target node is new record.');
        }
        if ($depth === 0 && $this->node->isRoot()) {
            throw new Exception('Can not insert a node before/after root.');
        }
        $this->setAttribute($this->getLeftAttributeName(), $to);
        $this->setAttribute($this->getRightAttributeName(), $to + 1);
        $this->setAttribute($this->getLevelAttributeName(), $this->node->getLevel() + $depth);
        $this->setAttribute($this->getParentIdName(), $this->node->getKey());

        $this->shift($to, null, 2);
    }

    /**
     * @param $from
     * @param $to
     * @param $delta
     */
    protected function shift($from, $to, $delta)
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

    /**
     * @return \yii\db\ActiveQuery
     */
    /*public function getParent()
    {
        $tableName = $this->owner->tableName();
        $query = $this->getParents(1)
            ->orderBy(["{$tableName}.[[{$this->leftAttribute}]]" => SORT_DESC])
            ->limit(1);
        $query->multiple = false;
        return $query;
    }*/

    /* protected function setParent($value)
     {
         $this->setParentId($value ? $value->getKey() : null)
             ->setRelation('parent', $value);

         return $this;
     }

     public function getParent()
     {
         $tableName = $this->owner->tableName();
         $query = $this->getParents(1)
             ->orderBy(["{$tableName}.[[{$this->leftAttribute}]]" => SORT_DESC])
             ->limit(1);
         $query->multiple = false;

         return $query;
     }


     public function getParent()
     {
         $this->setParentId($value ? $value->getKey() : null)
             ->setRelation('parent', $value);

         return $this;
     }


     public function setParentId($value)
     {
         $this->attributes[ $this->getParentIdName() ] = $value;

         return $this;
     }

     public function getParentId()
     {
         return $this->getAttributeValue($this->getParentIdName());
     }

     public function isRoot()
     {
         return is_null($this->getParentId());
     }*/


}
