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

    /** @var Config */
    protected $_tree_config;

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
     * @inheritDoc
     */
    protected function bootIfNotBooted()
    {
        static::registerModelEvent(
            'booting',
            static function ($model) {
                $model->setTreeConfig(static::buildTreeConfig());
            }
        );

        parent::bootIfNotBooted();
    }

    /**
     * Set tree-config to model
     *
     * @param Config $config
     */
    public function setTreeConfig(Config $config): void
    {
        $this->_tree_config = $config;
    }

    /**
     * @return Config|null
     */
    public function getTreeConfig(): ?Config
    {
        if (!$this->_tree_config) {
            $this->_tree_config = static::buildTreeConfig();
        }

        return $this->_tree_config;
    }

    /**
     * Build custom tree-config
     *
     * @return Config
     */
    protected static function buildTreeConfig(): Config
    {
        return new Config();
    }

    /**
     * @return string
     */
    public function getParentIdName(): string
    {
        return $this->getTreeConfig()->getParentAttributeName();
    }

    /**
     * @return string
     */
    public function getLeftAttributeName(): string
    {
        return $this->getTreeConfig()->getLeftAttributeName();
    }

    /**
     * @return string
     */
    public function getRightAttributeName(): string
    {
        return $this->getTreeConfig()->getRightAttributeName();
    }

    /**
     * @return string
     */
    public function getLevelAttributeName(): string
    {
        return $this->getTreeConfig()->getLevelAttributeName();
    }

    /**
     * @return string|null
     */
    public function getTreeAttributeName(): ?string
    {
        return $this->getTreeConfig()->getTreeAttributeName();
    }

    /**
     * @return bool
     */
    public function isMultiTree(): bool
    {
        return $this->getTreeConfig()->isMultiTree();
    }

    /**
     * Get the value of the model's parent id key.
     *
     * @return integer|string|null
     */
    public function getParentId()
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
     * @return int|mixed|null
     */
    public function getTree()
    {
        return $this->isMultiTree() ? $this->getAttributeValue($this->getTreeAttributeName()) : null;
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
        return array_map(
            function ($column) {
                return $this->getAttributeValue($column);
            },
            $this->getTreeConfig()->getColumns()
        );
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

        return $this->applyNestedSetScope($builder, $table);
    }

    /**
     * @param mixed $query
     * @param string $table
     *
     * @return mixed
     */
    public function applyNestedSetScope($query, $table = null)
    {
        if (!$scoped = $this->getScopeAttributes()) {
            return $query;
        }

        if (!$table) {
            $table = $this->getTable();
        }

        foreach ($scoped as $attribute) {
            $query->where($table . '.' . $attribute, '=', $this->getAttributeValue($attribute));
        }
        return $query;
    }

    /**
     * @return array
     */
    protected function getScopeAttributes(): array
    {
        return [];
    }

    /** @var array|null For increase `getCast` function */
    private $castsFill;

    /**
     * @return array
     */
    public function getCasts(): array
    {
        if ($this->castsFill === null) {
            $casts = array_merge(
                parent::getCasts(),
                [
                    $this->getLevelAttributeName() => 'integer',
                    $this->getLeftAttributeName() => 'integer',
                    $this->getRightAttributeName() => 'integer',
                ],
                $this->casts
            );

            if ($type = $this->getTreeConfig()->getCastForParentAttribute()) {
                $casts[$this->getParentIdName()] = $type;
            }

            if ($type = $this->getTreeConfig()->getCastForTreeAttribute()) {
                $casts[$this->getTreeAttributeName()] = $type;
            }

            $this->castsFill = $casts;
        }

        return $this->castsFill;
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

    /**
     * @param string|int|static $node
     *
     * @return array
     *
     */
    public function getNodeBounds($node): array
    {
        if (Config::isNode($node)) {
            return $node->getBounds();
        }

        return $this->newNestedSetQuery()->getPlainNodeData($node, true);
    }
}
