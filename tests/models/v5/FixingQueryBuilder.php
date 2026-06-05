<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\models\v5;

use Fureev\Trees\Contracts\TreeModel;
use Fureev\Trees\QueryBuilder\Fixing;
use Fureev\Trees\QueryBuilderV2;
use Illuminate\Database\Eloquent\Model;

/**
 * Query builder that exposes the tree-fixing helpers.
 *
 * @template TModel of Model&TreeModel
 *
 * @extends QueryBuilderV2<TModel>
 */
class FixingQueryBuilder extends QueryBuilderV2
{
    use Fixing;
}
