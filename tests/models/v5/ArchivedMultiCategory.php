<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\models\v5;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Several trees in one table, soft deleting. The combination the suite had no model for: a
 * trashed node keeps its bounds, and every tree numbers its own from 1, so the two conditions
 * that keep those facts apart meet only here.
 */
class ArchivedMultiCategory extends AbstractMultiModel
{
    use SoftDeletes;

    protected $fillable = ['title'];

    protected $table = 'archived_categories_multi';
}
