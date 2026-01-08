<?php

declare(strict_types=1);

namespace Fureev\Trees;

use Fureev\Trees\Config\Attribute;
use Illuminate\Database\Eloquent\Model;

trait UseConfigShorter
{
    public function leftAttribute(): Attribute
    {
        return $this->getTreeConfig()->left;
    }

    public function leftValue(): int
    {
        return $this->getAttributeValue((string)$this->leftAttribute());
    }

    public function rightAttribute(): Attribute
    {
        return $this->getTreeConfig()->right;
    }

    public function rightValue(): int
    {
        return $this->getAttributeValue((string)$this->rightAttribute());
    }

    public function levelAttribute(): Attribute
    {
        return $this->getTreeConfig()->level;
    }

    public function levelValue(): int
    {
        return $this->getAttributeValue((string)$this->levelAttribute());
    }

    public function parentAttribute(): Attribute
    {
        return $this->getTreeConfig()->parent;
    }

    public function parentValue(): int|string|null
    {
        return $this->getAttributeValue((string)$this->parentAttribute());
    }

    public function treeAttribute(): ?Attribute
    {
        return $this->getTreeConfig()->tree;
    }

    public function treeValue(): int|string|null
    {
        return $this->treeAttribute() !== null
            ? $this->attributes[(string)$this->treeAttribute()] ?? null
            : null;
    }

    protected function isSoftDelete(): bool
    {
        return $this->getTreeConfig()->isSoftDelete;
    }

    public function isRoot(): bool
    {
        return $this->parentValue() === null;
    }

    /**
     * @phpstan-param static $model
     */
    public function isEqualTo(Model $model): bool
    {
        return
            $this->leftValue() === $model->leftValue() &&
            $this->rightValue() === $model->rightValue() &&
            $this->levelValue() === $model->levelValue() &&
            $this->parentValue() === $model->parentValue() &&
            $this->treeValue() === $model->treeValue();
    }

    public function isLevel(int $level): bool
    {
        return $this->levelValue() === $level;
    }

    /**
     * @return array<int, int|string|null>
     */
    public function getBounds(): array
    {
        return array_map(
            fn($column) => $this->getAttributeValue($column),
            $this->getTreeConfig()->columnsNames()
        );
    }
}
