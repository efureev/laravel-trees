<?php

declare(strict_types=1);

namespace Fureev\Trees\Config;

final readonly class Config
{
    public function __construct(
        public bool $isSoftDelete,
        public string $childrenHandlerOnDelete,
        public string $deleterWithChildren,
        public Attribute $left,
        public Attribute $right,
        public Attribute $level,
        public Attribute $parent,
        public ?Attribute $tree = null,
    ) {
    }

    public function isMulti(): bool
    {
        return $this->tree !== null;
    }

    public function columnsList(): array
    {
        $tree = $this->tree;

        return array_merge(
            [
                $this->left,
                $this->right,
                $this->level,
                $this->parent,
            ],
            $tree !== null ? [$tree] : []
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
}
