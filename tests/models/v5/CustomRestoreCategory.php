<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\models\v5;

use Closure;

/**
 * Replaces the restore routines through the trait's static hooks.
 *
 * Both properties are redeclared so this class gets storage of its own — a static declared by
 * the trait is shared with every class that inherits it, and a test setting the parent's copy
 * would leak into the rest of the suite.
 */
class CustomRestoreCategory extends ArchivedCategory
{
    protected static ?Closure $customRestoreWithDescendantsFn = null;
    protected static ?Closure $customRestoreWithParentsFn     = null;

    public static function useCustomRestore(?Closure $parents = null, ?Closure $descendants = null): void
    {
        static::$customRestoreWithParentsFn     = $parents;
        static::$customRestoreWithDescendantsFn = $descendants;
    }
}
