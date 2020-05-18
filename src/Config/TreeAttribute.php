<?php

namespace Fureev\Trees\Config;

class TreeAttribute extends AbstractAttribute
{
    protected string $name = 'tree_id';

    /**
     * Auto generation ID for a new tree if TRUE. If FALSE: on empty treeId - will be exception.
     *
     * @var boolean
     */
    protected bool $autoGenerate = true;

    /**
     * @return bool
     */
    public function isAutoGenerate(): bool
    {
        return $this->autoGenerate;
    }

    public function setAutoGenerate(bool $enable = true): self
    {
        $this->autoGenerate = $enable;

        return $this;
    }
}
