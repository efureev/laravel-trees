<?php

declare(strict_types=1);

namespace Fureev\Trees\Generators;

use Fureev\Trees\Config\Attribute;
use Fureev\Trees\Config\FieldType;
use Fureev\Trees\Exceptions\Exception;
use Illuminate\Database\Eloquent\Model;
use Str;
use Symfony\Component\Uid\Uuid;

final readonly class TreeIdGenerator implements GeneratorTreeIdContract
{
    public function __construct(private Attribute $attribute)
    {
    }

    /**
     * Generates a new ID for the tree node based on the configured attribute type.
     *
     * @param Model $model The model to generate an ID for
     * @return string|int The generated ID (integer for numeric types, string for UUID)
     * @throws Exception When the ID type is not supported
     */
    public function generateId(Model $model): string|int
    {
        $fieldType = $this->attribute->type();

        return match (true) {
            $fieldType->isInteger() => $this->generateMaxId($model),
            $fieldType === FieldType::UUID => $this->generateUuid(),
            $fieldType === FieldType::ULID => $this->generateUlid(),
            default => throw new Exception('Unsupported field type for tree ID generation'),
        };
    }

    /**
     * Generates a sequential integer ID by finding the maximum existing ID and incrementing it.
     *
     * @param Model $model The model to generate an ID for
     * @return int The new maximum ID
     */
    private function generateMaxId(Model $model): int
    {
        $treeAttribute = (string)$model->treeAttribute();
        $maxId         = (int)$model->max($treeAttribute);

        return ($maxId + 1);
    }

    /**
     * Generates a new UUID v7 for use as a tree identifier.
     *
     * @return string The generated UUID as a string
     */
    private function generateUuid(): string
    {
        return (string)Uuid::v7();
    }

    /**
     * Generates a new ULID for use as a tree identifier.
     *
     * @return string The generated ULID as a string
     */
    private function generateUlid(): string
    {
        return strtolower((string)Str::ulid());
    }
}
