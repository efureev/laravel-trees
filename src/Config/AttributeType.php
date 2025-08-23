<?php

declare(strict_types=1);

namespace Fureev\Trees\Config;

enum AttributeType: string
{
    case Left   = 'lft';
    case Right  = 'rgt';
    case Level  = 'lvl';
    case Parent = 'parent_id';
    case Tree   = 'tree_id';

    public function isTreeType(): bool
    {
        return $this === self::Tree;
    }
}
