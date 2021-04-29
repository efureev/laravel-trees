<?php

namespace Fureev\Trees\Config;

use Fureev\Trees\Exceptions\Exception;

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

    /**
     * @throws Exception
     */
    public function setType(string $type): self
    {
        $typeOpt = Base::getCastForCustomAttribute($type);
        if ($typeOpt === null) {
            throw Exception::make("Invalid type {$type}");
        }

        $this->type = $typeOpt;

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
