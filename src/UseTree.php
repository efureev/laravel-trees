<?php

declare(strict_types=1);

namespace Fureev\Trees;

use Fureev\Trees\Config\Builder;
use Fureev\Trees\Config\Config;
use Fureev\Trees\Config\FieldType;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Model
 */
trait UseTree
{
    use UseNestedSet;
    use UseConfigShorter;

    protected Config $tree_config__;

    public function initializeUseTree(): void
    {
        $this->rebuildTreeConfig();
        $this->mergeTreeCasts();
    }

    protected function mergeTreeCasts(): void
    {
        $casts = [
            (string)$this->levelAttribute() => 'integer',
            (string)$this->leftAttribute()  => 'integer',
            (string)$this->rightAttribute() => 'integer',
        ];

        // $casts[(string)$this->parentAttribute()] = $this->parentAttribute()->type()->toModelCast();
        $casts[(string)$this->parentAttribute()] = $this->getKeyType();

        if (($treeAttr = $this->treeAttribute())) {
            $casts[(string)$treeAttr] = $treeAttr->type()->toModelCast();
        }

        $this->mergeCasts($casts);
    }

    public function getTreeBuilder(): Builder
    {
        $builder = static::buildTree();
        $builder->parent()->setType(FieldType::fromString($this->getKeyType()));

        return $builder;
    }

    public function getTreeConfig(): Config
    {
        return $this->tree_config__ ??= $this->getTreeBuilder()->build($this);
    }

    protected function rebuildTreeConfig(): void
    {
        $this->tree_config__ = $this->getTreeBuilder()->build($this);
    }

    protected static function buildTree(): Builder
    {
        return Builder::default();
    }
}
