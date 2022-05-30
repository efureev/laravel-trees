<?php

namespace Fureev\Trees;

use Fureev\Trees\Config\{Base, LeftAttribute, LevelAttribute, ParentAttribute, RightAttribute, TreeAttribute};
use Fureev\Trees\Contracts\NestedSetConfig;
use Illuminate\Database\Eloquent\Model;

use function method_exists;

/**
 * Trait BaseNestedSetTrait
 *
 * @property QueryBuilder $query
 */
trait BaseNestedSetTrait
{
    /** @var NestedSetConfig */
    protected $tree_config__;

    /**
     * @var integer
     */
    protected $operation;

    /**
     * @var Model|NestedSetTrait|BaseNestedSetTrait
     */
    protected $node;

    /**
     * Forced save
     *
     * @var boolean
     */
    protected bool $forceSave = false;

    /**
     * @var array<string,string>|null For boost `getCast` function
     */
    private ?array $castsFill = null;

    /**
     * @inheritDoc
     */
    protected function bootIfNotBooted()
    {
        static::registerModelEvent(
            'booting',
            static function ($model) {
                /** @var $model static */
                $config = static::buildTreeConfig();
                $config->parent()->setType($model->getKeyType());
                $model->setTreeConfig($config);
            }
        );

        parent::bootIfNotBooted();
    }

    /**
     * Build custom tree-config
     *
     * @return Base
     */
    protected static function buildTreeConfig(): Base
    {
        return new Base();
    }

    /**
     * @return NestedSetConfig
     */
    public function getTreeConfig(): NestedSetConfig
    {
        if (!$this->tree_config__) {
            $this->tree_config__ = static::buildTreeConfig();
            $this->tree_config__->parent()->setType($this->getKeyType());
        }

        return $this->tree_config__;
    }

    /**
     * Set tree-config to model
     *
     * @param NestedSetConfig $config
     */
    public function setTreeConfig(NestedSetConfig $config): void
    {
        $this->tree_config__ = $config;
    }


    /**
     * @return bool
     */
    public function isMultiTree(): bool
    {
        if ($this->node) {
            return $this->node->getTreeConfig()->isMultiTree();
        }

        return $this->getTreeConfig()->isMultiTree();
    }


    /**
     * Get the value of the model's level
     *
     * @return int
     */
    /*
    public function getLevel(): ?int
    {
        return $this->getAttributeValue($this->getLevelAttributeName());
    }*/

    /**
     * @return string
     */
    /*public function getLevelAttributeName(): string
    {
        return $this->getTreeConfig()->level()->name();
    }*/

    public function levelAttribute(): LevelAttribute
    {
        return $this->getTreeConfig()->level();
    }

    public function levelValue(): int
    {
        return $this->getAttributeValue($this->levelAttribute()->name());
    }

    /**
     * @return TreeAttribute|null
     */
    public function treeAttribute(): ?TreeAttribute
    {
        return $this->getTreeConfig()->tree();
    }

    /**
     * @return string|null
     */
    /*public function getTreeAttributeName(): ?string
    {
        return $this->getTreeConfig()->getTreeAttributeName();
    }*/


    /**
     * @return int|string|null
     */
    public function treeValue()
    {
        return $this->treeAttribute() ? $this->getAttributeValue($this->treeAttribute()->name()) : null;
    }


    public function leftAttribute(): LeftAttribute
    {
        return $this->getTreeConfig()->left();
    }

    public function leftOffset(): int
    {
        return $this->getAttributeValue($this->leftAttribute()->name());
    }

    public function rightAttribute(): RightAttribute
    {
        return $this->getTreeConfig()->right();
    }

    public function rightOffset(): int
    {
        return $this->getAttributeValue($this->rightAttribute()->name());
    }

    public function parentAttribute(): ParentAttribute
    {
        return $this->getTreeConfig()->parent();
    }

    public function parentValue()
    {
        return $this->getAttributeValue($this->parentAttribute()->name());
    }


    /**
     * @return string
     */
    /*public function getParentIdName(): string
    {
        return $this->getTreeConfig()->getParentAttributeName();
    }*/

    /**
     * @return int
     * @see leftOffset()
     */
    /*public function getLeftOffset(): int
    {
        return $this->getAttributeValue($this->getLeftAttributeName());
    }*/

    /**
     * @return string
     */
    /*public function getLeftAttributeName(): string
    {
        return $this->getTreeConfig()->getLeftAttributeName();
    }*/

    /**
     * @return int
     * @see rightOffset
     */
    /*public function getRightOffset(): int
    {
        return $this->getAttributeValue($this->getRightAttributeName());
    }*/

    /**
     * @return string
     */
    /*public function getRightAttributeName(): string
    {
        return $this->getTreeConfig()->getRightAttributeName();
    }*/

    /**
     * {@inheritdoc}
     */
    public function newEloquentBuilder($query): QueryBuilder
    {
        return new QueryBuilder($query);
    }

    /**
     * @return array<string,string>
     */
    public function getCasts(): array
    {
        return $this->castsFill ??= array_merge(
            parent::getCasts(),
            $this->getCastsTree(),
            $this->casts
        );
    }

    /**
     * @return array<string,string>
     */
    public function getCastsTree(): array
    {
        $casts = [
            $this->levelAttribute()->name() => 'integer',
            $this->leftAttribute()->name()  => 'integer',
            $this->rightAttribute()->name() => 'integer',
        ];

        if ($type = $this->getTreeConfig()->getCastForParentAttribute()) {
            $casts[$this->parentAttribute()->name()] = $type;
        }

        if ($this->treeAttribute() && ($type = $this->getTreeConfig()->getCastForTreeAttribute())) {
            $casts[$this->treeAttribute()->name()] = $type;
        }

        return $casts;
    }


    /**
     * @return mixed
     */
    public function getDirty()
    {
        $dirty = parent::getDirty();

        if (!$dirty && $this->forceSave) {
            $dirty[$this->parentAttribute()->name()] = $this->parentValue();
        }

        return $dirty;
    }

    /**
     * Get the value of the model's parent id key.
     *
     * @return integer|string|null
     */
    /*public function getParentId()
    {
        return $this->getAttributeValue($this->getParentIdName());
    }*/

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
     * @param string|int|static|Model $node
     *
     * @return array
     *
     */
    public function getNodeBounds($node): array
    {
        if (Base::isNode($node)) {
            return $node->getBounds();
        }

        return $this->newNestedSetQuery()->getPlainNodeData($node, true);
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
            $this->getTreeConfig()->columns()
        );
    }

    /**
     * @param string|null $table
     *
     * @return QueryBuilder
     */
    public function newNestedSetQuery(string $table = null): QueryBuilder
    {
        $builder = self::isSoftDelete()
            ? $this->withTrashed()
            : $this->newQuery();

        return $this->applyNestedSetScope($builder, $table);
    }

    /**
     * @return bool
     */
    public static function isSoftDelete(): bool
    {
        return method_exists(static::class, 'bootSoftDeletes');
    }

    public function newScopedQuery($table = null)
    {
        return $this->applyNestedSetScope($this->newQuery(), $table);
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
