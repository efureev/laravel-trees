<?php

namespace Fureev\Trees\Tests\models;

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
    protected $keyType = 'string';

    protected $table = 'pages_uuid';
}
