<?php

namespace Fureev\Trees;

use Illuminate\Database\Eloquent\Model;

/**
 * Trait BaseNestedSetTrait
 *
 * @property QueryBuilder $query
 */
trait BaseNestedSetTrait
{
    /** @var NestedSetConfig */
    protected $_config;

    /** @var string */
    protected $_configClass = NestedSetConfig::class;

    /** @var int */
    protected $operation;

    /** @var Model|NestedSetTrait|BaseNestedSetTrait */
    protected $node;

    /**
     * Forced save
     *
     * @var bool
     */
    protected $forceSave = false;

    /**
     * @return string
     */
    public function getTreeConfigName(): string
    {
        return $this->_configClass;
    }

    /**
     * @return NestedSetConfig
     */
    public function treeConfig(): NestedSetConfig
    {
        if (!$this->_config) {
            $cls = $this->getTreeConfigName();

            $this->_config = new $cls;
        }

        return $this->_config;
    }

    /**
     * @return string
     */
    public function getParentIdName(): string
    {
        return $this->treeConfig()->parentAttribute;
    }

    /**
     * @return string
     */
    public function getLeftAttributeName(): string
    {
        return $this->treeConfig()->leftAttribute;
    }

    /**
     * @return string
     */
    public function getRightAttributeName(): string
    {
        return $this->treeConfig()->rightAttribute;
    }

    /**
     * @return string
     */
    public function getLevelAttributeName(): string
    {
        return $this->treeConfig()->levelAttribute;
    }

    /**
     * @return string
     */
    public function getTreeAttributeName(): string
    {
        return $this->treeConfig()->treeAttribute;
    }

    /**
     * @return bool
     */
    public function isMultiTree(): bool
    {
        return $this->treeConfig()->treeAttribute !== null;
    }

    /**
     * Get the value of the model's parent id key.
     *
     * @return integer|null
     */
    public function getParentId(): ?int
    {
        return $this->getAttributeValue($this->getParentIdName());
    }

    /**
     * Get the value of the model's level
     *
     * @return int
     */
    public function getLevel(): ?int
    {
        return $this->getAttributeValue($this->getLevelAttributeName());
    }

    /**
     * @return int
     */
    public function getLeftOffset(): int
    {
        return $this->getAttributeValue($this->getLeftAttributeName());
    }

    /**
     * @return int
     */
    public function getRightOffset(): int
    {
        return $this->getAttributeValue($this->getRightAttributeName());
    }

    /**
     * @return array
     */
    public function getBounds(): array
    {
        return [$this->getLeftOffset(), $this->getRightOffset()];
    }

    /**
     * {@inheritdoc}
     */
    public function newEloquentBuilder($query): QueryBuilder
    {
        return new QueryBuilder($query);
    }

    /**
     * @param string|null $table
     *
     * @return QueryBuilder
     */
    public function newNestedSetQuery($table = null): QueryBuilder
    {
        $builder = self::isSoftDelete()
            ? $this->withTrashed()
            : $this->newQuery();

        return $builder;
    }


    /**
     * @return array
     */
    public function getCasts(): array
    {
        $this->casts = parent::getCasts();

        $this->casts = array_merge([
            $this->getLevelAttributeName() => 'integer',
            $this->getLeftAttributeName() => 'integer',
            $this->getRightAttributeName() => 'integer',
            $this->getParentIdName() => 'integer',
        ], $this->casts);

        return $this->casts;
    }

    /**
     * @return bool
     */
    public static function isSoftDelete(): bool
    {
        return \method_exists(static::class, 'bootSoftDeletes');
    }

    /**
     * @return mixed
     */
    public function getDirty()
    {
        $dirty = parent::getDirty();

        if (!$dirty && $this->forceSave) {
            $dirty[$this->getParentIdName()] = $this->getParentId();
        }

        return $dirty;
    }

    /**
     * @return mixed
     */
    public function forceSave()
    {
        $this->forceSave = true;

        return $this->save();
    }

    /**
     * @return bool
     */
    public function isForceSaving(): bool
    {
        return $this->forceSave;
    }

    /**
     *
     */
    protected function nodeRefresh(): void
    {
        if ($this->node !== null && $this->node->exists) {
            $this->node->refresh();
        }
    }
}
