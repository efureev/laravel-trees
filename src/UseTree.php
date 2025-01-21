<?php

declare(strict_types=1);

namespace Fureev\Trees;

use Fureev\Trees\Config\Builder;
use Fureev\Trees\Config\Config;
use Fureev\Trees\Config\FieldType;
use Fureev\Trees\Exceptions\Exception;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 *
 * @method static static byTree(int|string $treeId)
 * @method static static root()
 *
 * @mixin QueryBuilderV2<static>
 * @mixin Model
 */
trait UseTree
{
    /** @use UseNestedSet<TModel> */
    use UseNestedSet;
    use UseConfigShorter;

    private Config $tree_config__;

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

        $casts[(string)$this->parentAttribute()] = $this->getKeyType();

        if (($treeAttr = $this->treeAttribute())) {
            $casts[(string)$treeAttr] = $treeAttr->type()->toModelCast();
        }

        $this->mergeCasts($casts);
    }

    /**
     * @throws Exception
     */
    public function getTreeBuilder(): Builder
    {
        $builder = static::buildTree();
        $builder->parent()->setType(FieldType::fromString($this->getKeyType()));

        return $builder;
    }

    /**
     * @throws Exception
     */
    public function getTreeConfig(): Config
    {
        return $this->tree_config__ ??= $this->getTreeBuilder()->build($this);
    }

    /**
     * @throws Exception
     */
    protected function rebuildTreeConfig(): void
    {
        $this->tree_config__ = $this->getTreeBuilder()->build($this);
    }

    protected static function buildTree(): Builder
    {
        return Builder::default();
    }
}
