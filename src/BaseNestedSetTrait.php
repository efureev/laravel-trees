<?php

namespace Fureev\Trees;

/**
 * Trait BaseNestedSetTrait
 *
 * @package Fureev\Trees
 * @property \Fureev\Trees\QueryBuilder $query
 */
trait BaseNestedSetTrait
{
    /** @var \Fureev\Trees\NestedSetConfig */
    protected $_config;

    /** @var string */
    protected $_configClass = NestedSetConfig::class;

    protected $operation;

    /** @var \Illuminate\Database\Eloquent\Model|\Fureev\Trees\NestedSetTrait|\Fureev\Trees\BaseNestedSetTrait */
    protected $node;

    /**
     * @return string
     */
    public function getTreeConfigName(): string
    {
        return $this->_configClass;
    }

    /**
     * @return \Fureev\Trees\NestedSetConfig
     */
    public function treeConfig()
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
        $builder = $this->isSoftDelete()
            ? $this->withTrashed()
            : $this->newQuery();

//        return $this->applyNestedSetScope($builder, $table);
        return $builder;
    }

    /**
     * @param mixed $query
     * @param string $table
     *
     * @return QueryBuilder
     */
    /* public function applyNestedSetScope($query, $table = null): QueryBuilder
     {
         if (!$scoped = $this->getScopeAttributes()) {
             return $query;
         }
         if (!$table) {
             $table = $this->getTable();
         }

         foreach ($scoped as $attribute) {
             $query->where($table . '.' . $attribute, '=',
                 $this->getAttributeValue($attribute));
         }

         return $query;
     }*/

    /**
     * @return array
     */
    /*    protected function getScopeAttributes()
        {
            return null;
        }*/

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
}
