<?php

namespace Fureev\Trees\Config;

use Fureev\Trees\Exceptions\Exception;
use Php\Support\Traits\Maker;

/**
 * Class AbstractAttribute
 * @package Fureev\Trees\Config
 */
abstract class AbstractAttribute
{
    use Maker;

    protected string $name;

    protected string $type = 'unsignedInteger';

    protected bool $nullable = false;

    /**
     * @var mixed
     */
    protected $default;

    public function __construct(string $type = null)
    {
        if ($type) {
            $this->setType($type);
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
            throw Exception::make("Invalid type $type");
        }

        $this->type = $typeOpt;

        return $this;
    }

    public function setUuidType(): self
    {
        $this->type = 'uuid';

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
