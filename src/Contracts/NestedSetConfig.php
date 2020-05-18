<?php

namespace Fureev\Trees\Contracts;

use Fureev\Trees\Config\LeftAttribute;
use Fureev\Trees\Config\LevelAttribute;
use Fureev\Trees\Config\ParentAttribute;
use Fureev\Trees\Config\RightAttribute;
use Fureev\Trees\Config\TreeAttribute;

interface NestedSetConfig
{
    /**
     * Get a list of columns.
     *
     * @param bool $names
     *
     * @return array
     */
    public function columns($names = true): array;

    public function parent(): ParentAttribute;

    public function level(): LevelAttribute;

    public function left(): LeftAttribute;

    public function right(): RightAttribute;

    public function tree(): ?TreeAttribute;

    public function indexes(): array;
}
