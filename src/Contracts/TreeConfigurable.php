<?php

namespace Fureev\Trees\Contracts;

interface TreeConfigurable
{
    public function getTreeConfig(): NestedSetConfig;
}
