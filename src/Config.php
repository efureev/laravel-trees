<?php

namespace Fureev\Trees;

use Illuminate\Database\Eloquent\Model;
use Php\Support\Traits\ConfigurableTrait;

class Config implements Contracts\NestedSetConfig
{
    use ConfigurableTrait;

    public const OPERATION_MAKE_ROOT     = 1;
    public const OPERATION_PREPEND_TO    = 2;
    public const OPERATION_APPEND_TO     = 3;
    public const OPERATION_INSERT_BEFORE = 4;
    public const OPERATION_INSERT_AFTER  = 5;
    public const OPERATION_DELETE_ALL    = 6;

    /**
     * @var string
     */
    protected $leftAttribute = 'lft';

    /**
     * @var string
     */
    protected $rightAttribute = 'rgt';

    /**
     * @var string
     */
    protected $levelAttribute = 'lvl';

    /**
     * Name of `parent` column
     *
     * @var string
     */
    protected $parentAttribute = 'parent_id';
    /**
     * Type of `parent` column
     *
     * @var string
     */
    protected $parentAttributeType = 'unsignedInteger';


    /**
     * Type of `tree` column
     *
     * @var string
     */
    protected $treeAttributeType = 'unsignedInteger';

    /**
     * Prefix for multi-tree node
     *
     * @var string|null
     */
    protected $treeAttribute;

    /**
     * Auto generation ID for a new tree if TRUE. If FALSE: on empty treeId - will be exception.
     *
     * @var boolean
     */
    protected $autoGenerateTreeId = true;

    /**
     * @var Model
     */
    protected $node;


    public function __construct(array $params = [])
    {
        $this->configurable($params);
    }

    /**
     * @param Model $model
     *
     * @return bool
     */
    public static function isNode($model): bool
    {
        return is_object($model) && (class_uses_recursive($model)[NestedSetTrait::class]) ?? null;
    }

    /**
     * Get a list of default columns.
     *
     * @return array
     */
    public function getColumns(): array
    {
        return array_merge(
            [
                $this->getLeftAttributeName(),
                $this->getRightAttributeName(),
                $this->getLevelAttributeName(),
                $this->getParentAttributeName(),
            ],
            $this->isMultiTree()
                ? [$this->getTreeAttributeName()]
                : []
        );
    }

    /**
     * @return string
     */
    public function getLeftAttributeName(): string
    {
        return $this->leftAttribute;
    }

    /**
     * @return string
     */
    public function getRightAttributeName(): string
    {
        return $this->rightAttribute;
    }

    /**
     * @return string
     */
    public function getLevelAttributeName(): string
    {
        return $this->levelAttribute;
    }

    /**
     * @return string
     */
    public function getParentAttributeName(): string
    {
        return $this->parentAttribute;
    }

    /**
     * @return bool
     */
    public function isMultiTree(): bool
    {
        return $this->treeAttribute !== null;
    }

    /**
     * @return string|null
     */
    public function getTreeAttributeName(): ?string
    {
        return $this->treeAttribute;
    }

    /**
     * @return string
     */
    public function getCastForParentAttribute(): ?string
    {
        return static::getCastForCustomAttribute($this->getParentAttributeType());
    }

    /**
     * @param string $attributeType
     *
     * @return string|null
     */
    protected static function getCastForCustomAttribute(string $attributeType): ?string
    {
        switch ($attributeType) {
            case 'integer':
            case 'unsignedInteger':
                return 'integer';
            case 'string':
            case 'uuid':
                return 'uuid';
        }

        return null;
    }

    /**
     * @return string
     */
    public function getParentAttributeType(): string
    {
        return $this->parentAttributeType;
    }

    /**
     * @return string|null
     */
    public function getCastForTreeAttribute(): ?string
    {
        return static::getCastForCustomAttribute($this->getTreeAttributeType());
    }

    /**
     * @return string
     */
    public function getTreeAttributeType(): string
    {
        return $this->treeAttributeType;
    }

    /**
     * @return bool
     */
    public function isAutoGenerateTreeId(): bool
    {
        return $this->autoGenerateTreeId;
    }

    /**
     * Generate function
     *
     * @param Model $model
     *
     * @return mixed
     */
    public function generateTreeId($model)
    {
        if (method_exists($model, 'generateTreeId')) {
            return $model->generateTreeId();
        }

        return (((int)$model->max($this->getTreeAttributeName())) + 1);
    }
}
