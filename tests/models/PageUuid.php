<?php

namespace Fureev\Trees\Tests\models;

use Fureev\Trees\Config\Base;

/**
 * Class Page
 *
 * @package Fureev\Trees\Tests\models
 * @property string $id
 * @property string $parent_id
 *
 * @mixin \Fureev\Trees\QueryBuilder
 */
class PageUuid extends Page
{
    protected $keyType = 'uuid';

    protected $table = 'pages_uuid';

    protected static function buildTreeConfig(): Base
    {
        return new Base(true);
    }
}
