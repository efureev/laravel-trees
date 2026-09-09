<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\models\v5;

/**
 * Overrides `getScopeAttributes()`, the hook that adds a fixed condition to every query the
 * package builds. Nothing in `src/` returns a non-empty list, so only a model can exercise it.
 */
class ScopedCategory extends AbstractModel
{
    protected $fillable = [
        'title',
        'path',
    ];

    protected $table = 'scoped_categories';

    /**
     * @return string[]
     */
    protected function getScopeAttributes(): array
    {
        return ['path'];
    }
}
