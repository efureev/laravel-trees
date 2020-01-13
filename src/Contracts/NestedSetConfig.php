<?php

namespace Fureev\Trees\Contracts;

interface NestedSetConfig
{
    /**
     * Get a list of columns.
     *
     * @return array
     */
    public function getColumns(): array;

    /**
     * @return string
     */
    public function getParentAttributeName(): string;

    /**
     * @return string
     */
    public function getLeftAttributeName(): string;

    /**
     * @return string
     */
    public function getRightAttributeName(): string;

    /**
     * @return string
     */
    public function getLevelAttributeName(): string;

    /**
     * @return string
     */
    public function getTreeAttributeName(): ?string;

    /**
     * @return bool
     */
    public function isAutoGenerateTreeId(): bool;
}
