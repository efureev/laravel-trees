<?php

declare(strict_types=1);

namespace Fureev\Trees\Config;

use Php\Support\Traits\Maker;

/**
 * @method static static make(AttributeType $name, FieldType $type = FieldType::UnsignedInteger)
 */
class Attribute
{
    use Maker;

    protected string $column;

    protected bool $nullable = false;

    protected mixed $default = null;

    public function __construct(
        protected AttributeType $name,
        protected FieldType $type = FieldType::UnsignedInteger
    ) {
    }

    public function name(): AttributeType
    {
        return $this->name;
    }

    public function type(): FieldType
    {
        return $this->type;
    }

    public function default(): mixed
    {
        return $this->default;
    }

    public function setDefault(mixed $value): static
    {
        $this->default = $value;

        return $this;
    }

    public function setType(FieldType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function setName(AttributeType $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function nullable(): bool
    {
        return $this->nullable;
    }

    public function setNullable(bool $isNull = true): static
    {
        $this->nullable = $isNull;

        return $this;
    }

    public function setColumnName(string $column): static
    {
        $this->column = $column;

        return $this;
    }

    public function columnName(): string
    {
        return ($this->column ?? $this->name->value);
    }

    public function __toString(): string
    {
        return $this->columnName();
    }
}
