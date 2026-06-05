<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\models\v5;

use Illuminate\Database\Eloquent\Model;

/**
 * A plain Eloquent model that does NOT use the tree trait.
 * Used to assert that Healthy checks reject non-tree models.
 */
class NonTreeModel extends Model
{
    protected $table = 'non_tree';

    public $timestamps = false;
}
