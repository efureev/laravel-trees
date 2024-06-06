<?php

declare(strict_types=1);

namespace Fureev\Trees\Config;

use Fureev\Trees\Exceptions\Exception;

enum FieldType: string
{
    case UnsignedSmallInteger  = 'unsignedSmallInteger';
    case UnsignedMediumInteger = 'unsignedMediumInteger';
    case UnsignedBigInteger    = 'unsignedBigInteger';
    case UnsignedInteger       = 'unsignedInteger';

    case UUID = 'uuid';
    case ULID = 'ulid';

    public function isInteger(): bool
    {
        return $this === self::UnsignedSmallInteger ||
            $this === self::UnsignedMediumInteger ||
            $this === self::UnsignedBigInteger ||
            $this === self::UnsignedInteger;
    }

    public static function fromString(string $value): self
    {
        return match (true) {
            $value === 'int' => self::UnsignedInteger,
            $value === 'uuid' => self::UUID,
            $value === 'ulid' => self::ULID,
            $value === 'string' => self::UUID,
            default => throw new Exception("Invalid type: $value"),
        };
    }

    public function toModelCast(): string
    {
        return match (true) {
            $this->isInteger() => 'integer',
            $this === self::UUID => 'string', // todo: need cast to UUID
            $this === self::ULID => 'string', // todo: need cast to ULID
            default => 'string',
        };
    }
}
