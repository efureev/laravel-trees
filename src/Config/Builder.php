<?php

declare(strict_types=1);

namespace Fureev\Trees\Config;

use Fureev\Trees\Strategy\DeleteWithChildren;
use Fureev\Trees\Strategy\MoveChildrenToParent;
use Illuminate\Database\Eloquent\Model;
use Php\Support\Traits\Maker;

/**
 * @method static Builder make()
 */
class Builder
{
    use Maker;

    protected Attribute $left;

    protected Attribute $right;

    protected Attribute $level;

    protected Attribute $parent;

    protected ?Attribute $tree = null;

    protected string $deleterWithChildren;

    protected string $childrenHandlerOnDelete;

    public function left(): Attribute
    {
        return $this->left;
    }

    public function right(): Attribute
    {
        return $this->right;
    }

    public function level(): Attribute
    {
        return $this->level;
    }

    public function parent(): Attribute
    {
        return $this->parent;
    }

    public function tree(): ?Attribute
    {
        return $this->tree;
    }

    public function setAttribute(Attribute $attribute): self
    {
        $name        = lcfirst($attribute->name()->name);
        $this->$name = $attribute;

        return $this;
    }

    public function setAttributes(Attribute ...$attributes): self
    {
        foreach ($attributes as $attribute) {
            $this->setAttribute($attribute);
        }

        return $this;
    }

    public static function default(): self
    {
        return static::make()->setAttributes(...self::attributesForUnoTree());
    }

    public static function defaultMulti(): self
    {
        return static::make()->setAttributes(...self::attributesForMultiTree());
    }

    /**
     * @return Attribute[]
     */
    public static function attributesForUnoTree(): array
    {
        return [
            new Attribute(AttributeType::Left),
            new Attribute(AttributeType::Right),
            new Attribute(AttributeType::Level),
            Attribute::make(AttributeType::Parent)->setNullable(),
        ];
    }

    /**
     * @return Attribute[]
     */
    public static function attributesForMultiTree(): array
    {
        $list   = self::attributesForUnoTree();
        $list[] = new Attribute(AttributeType::Tree);

        return $list;
    }

    /**
     * @return Attribute[]
     */
    public function columnsList(): array
    {
        return array_merge(
            [
                $this->left(),
                $this->right(),
                $this->level(),
                $this->parent(),
            ],
            $this->isMulti() ? [$this->tree()] : []
        );
    }

    /**
     * @return string[]
     */
    public function columnsNames(): array
    {
        return array_map(
            static fn(Attribute $attribute) => (string)$attribute,
            $this->columnsList()
        );
    }

    public function isMulti(): bool
    {
        return $this->tree !== null;
    }

    public function setDeleterWithChildren(string $value): static
    {
        $this->deleterWithChildren = $value;

        return $this;
    }

    protected function getDeleterWithChildren(): string
    {
        return ($this->deleterWithChildren ?? DeleteWithChildren::class);
    }

    protected function getChildrenHandlerOnDelete(): string
    {
        return ($this->childrenHandlerOnDelete ?? MoveChildrenToParent::class);
    }

    public function setChildrenHandlerOnDelete(string $value): static
    {
        $this->childrenHandlerOnDelete = $value;

        return $this;
    }

    public function columnIndexes(): array
    {
        return [
            (string)$this->right  => (string)$this->right,
            (string)$this->parent => (string)$this->parent,
            (string)$this->left   => [
                (string)$this->left,
                (string)$this->right,
            ],
        ];
    }

    public function build(Model $model): Config
    {
        return new Config(
            Helper::isModelSoftDeletable($model),
            $this->getChildrenHandlerOnDelete(),
            $this->getDeleterWithChildren(),
            ...$this->columnsList()
        );
    }
}
