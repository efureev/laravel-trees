<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\models\v5;

use Fureev\Trees\Config\Builder;

/**
 * Names a generator class that does not implement the generator contract.
 */
class BadGeneratorCategory extends AbstractModel
{
    protected $fillable = ['title'];

    protected $table = 'bad_generator_categories';

    protected static function buildTree(): Builder
    {
        return Builder::defaultMulti();
    }

    protected function treeIdGenerator(): ?string
    {
        return NotAGenerator::class;
    }
}
