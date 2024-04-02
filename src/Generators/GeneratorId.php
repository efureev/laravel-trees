<?php

declare(strict_types=1);

namespace Fureev\Trees\Generators;

use Fureev\Trees\Config\Attribute;
use Fureev\Trees\Config\FieldType;
use Fureev\Trees\Exceptions\Exception;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Uid\Uuid;

final readonly class GeneratorId implements GeneratorContract
{
    public function __construct(private Attribute $attribute)
    {
    }

    public function generate(Model $model): string|int
    {
        return match (true) {
            $this->attribute->type()->isInteger() => $this->generateMaxId($model),
            $this->attribute->type() === FieldType::UUID => $this->generateUuid($model),
            default => throw new Exception('Not implemented'),
        };
    }

    protected function generateMaxId(Model $model): mixed
    {
        return (((int)$model->max((string)$model->treeAttribute())) + 1);
    }

    protected function generateUuid(Model $model): mixed
    {
        return Uuid::v7();
    }
}
