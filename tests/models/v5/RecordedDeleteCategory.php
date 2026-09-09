<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\models\v5;

use Fureev\Trees\Config\Builder;

/**
 * Replaces the strategy behind `deleteWithChildren()` rather than the one behind a plain
 * `delete()` — the other half of the pair `StrictCategory` covers.
 */
class RecordedDeleteCategory extends AbstractModel
{
    protected $fillable = ['title'];

    protected $table = 'recorded_delete_categories';

    protected static function buildTree(): Builder
    {
        return Builder::default()
            ->setDeleterWithChildren(RecordingDeleter::class);
    }
}
