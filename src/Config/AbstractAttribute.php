<?php

namespace Fureev\Trees\Config;

/**
 * Class AbstractAttribute
 * @package Fureev\Trees\Config
 */
abstract class AbstractAttribute
{
    protected string $name;

    protected string $type = 'unsignedInteger';

    protected bool $nullable = false;

    /**
     * @var mixed
     */
    protected $default;

    protected array $params = [];

    public function __construct(string $name = null)
    {
        if ($name) {
            $this->name = $name;
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function default()
    {
        return $this->default;
    }

    /*   public function hasIndex(): bool
       {
           return $this->hasIndex;
       }*/

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function nullable(): bool
    {
        return $this->nullable;
    }

    public function setNullable(bool $isNull = true): self
    {
        $this->nullable = $isNull;

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
