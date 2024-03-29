<?php

namespace Fureev\Trees\Config;

use Fureev\Trees\Contracts\NestedSetConfig;
use Fureev\Trees\Exceptions\Exception;
use Fureev\Trees\NestedSetTrait;
use Illuminate\Database\Eloquent\Model;
use Php\Support\Traits\Maker;

class Base implements NestedSetConfig
{
    use Maker;

    public const OPERATION_MAKE_ROOT         = 1;
    public const OPERATION_PREPEND_TO        = 2;
    public const OPERATION_APPEND_TO         = 3;
    public const OPERATION_INSERT_BEFORE     = 4;
    public const OPERATION_INSERT_AFTER      = 5;
    public const OPERATION_DELETE_ALL        = 6;
    public const OPERATION_RESTORE_SELF_ONLY = 7;

    protected LeftAttribute $left;

    protected RightAttribute $right;

    protected LevelAttribute $level;

    protected ParentAttribute $parent;
    /**
     * @var null|TreeAttribute
     */
    protected $tree;


    /**
     * @var Model|null
     */
    protected $node;

    public function __construct($multi = false)
    {
        $this->reset();

        if ($multi) {
            $this->setMultiTree($multi);
        }
    }

    public function reset(): void
    {
        $this->left   = new LeftAttribute();
        $this->right  = new RightAttribute();
        $this->level  = new LevelAttribute();
        $this->parent = new ParentAttribute();
    }

    public function left(): LeftAttribute
    {
        return $this->left;
    }

    public function right(): RightAttribute
    {
        return $this->right;
    }

    public function level(): LevelAttribute
    {
        return $this->level;
    }

    public function parent(): ParentAttribute
    {
        return $this->parent;
    }

    public function tree(): ?TreeAttribute
    {
        return $this->tree;
    }

    public function setAttribute(string $name, AbstractAttribute $attribute): self
    {
        if (!property_exists($this, $name)) {
            throw Exception::make("Attribute $name is missing");
        }

        $this->$name = $attribute;

        return $this;
    }

    public function setAttributeTree(TreeAttribute $attribute): self
    {
        $this->tree = $attribute;

        return $this;
    }

    /**
     * Get a list of default columns.
     *
     * @param bool $names
     *
     * @return array
     */
    public function columns($names = true): array
    {
        $list = array_merge(
            [
                $this->left(),
                $this->right(),
                $this->level(),
                $this->parent(),
            ],
            $this->isMultiTree()
                ? [$this->tree()]
                : []
        );

        if (!$names) {
            return $list;
        }

        return array_map(
            static function (AbstractAttribute $item) {
                return (string)$item;
            },
            $list
        );
    }

    /**
     * @return bool
     */
    public function isMultiTree(): bool
    {
        return $this->tree !== null;
    }

    public function setMultiTree($treeAttribute = null): self
    {
        if ($treeAttribute === false) {
            $treeAttribute = null;
        } else {
            if ($treeAttribute === null || $treeAttribute === true) {
                $treeAttribute = new TreeAttribute();
            }
        }

        $this->tree = $treeAttribute;

        return $this;
    }


    /**
     * @param Model $model
     *
     * @return bool
     */
    public static function isNode($model): bool
    {
        return is_object($model) && (class_uses_recursive($model)[NestedSetTrait::class] ?? null);
    }


    /**
     * @param string $attributeType
     * @param bool $forModel For a Model or a Migration
     *
     * @return string|null
     */
    public static function getCastForCustomAttribute(string $attributeType, bool $forModel = false): ?string
    {
        switch ($attributeType) {
            case 'int':
            case 'integer':
            case 'unsignedInteger':
                return 'integer';
            case 'string':
            case 'uuid':
                return $forModel ? 'string' : 'uuid';
        }

        return null;
    }

    /**
     * @return string|null
     */
    public function getCastForParentAttribute(): ?string
    {
        return static::getCastForCustomAttribute($this->parent->type(), true);
    }

    /**
     * @return string|null
     */
    public function getCastForTreeAttribute(): ?string
    {
        return static::getCastForCustomAttribute($this->tree ?: $this->tree->type());
    }


    /**
     * Generate function
     *
     * @param Model|NestedSetTrait $model
     *
     * @return mixed
     */
    public function generateTreeId(Model $model): mixed
    {
        if (method_exists($model, 'generateTreeId')) {
            return $model->generateTreeId();
        }

        return (((int)$model->max($this->tree())) + 1);
    }

    /**
     * Create indexes into DB
     *
     * @return array
     */
    public function indexes(): array
    {
        return [
            $this->right->name()  => $this->right->name(),
            $this->parent->name() => $this->parent->name(),
            $this->left->name()   => [
                $this->left->name(),
                $this->right->name(),
            ],
        ];
    }
}
