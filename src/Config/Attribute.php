<?php

declare(strict_types=1);

namespace Fureev\Trees\Config;

/**
 * @method static static make(AttributeType $name, FieldType $type = FieldType::UnsignedInteger)
 */
class Attribute
{
    protected ?string $column = null;

    protected bool $nullable = false;

    protected mixed $default = null;

    public function __construct(
        protected AttributeType $name,
        protected FieldType $type = FieldType::UnsignedInteger
    ) {
    }

    /**
     * Named rather than variadic: the two arguments are the whole of it, and spelling them out
     * is what tells a reader — and static analysis — what an attribute is made of.
     */
    public static function make(
        AttributeType $name,
        FieldType $type = FieldType::UnsignedInteger
    ): static {
        // @phpstan-ignore-next-line new.static — the constructor is not final by design
        return new static($name, $type);
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
