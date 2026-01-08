<?php

declare(strict_types=1);

namespace Fureev\Trees;

use Fureev\Trees\Config\Builder;
use Fureev\Trees\Config\Config;
use Fureev\Trees\Config\FieldType;
use Fureev\Trees\Exceptions\Exception;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 *
 * @method static QueryBuilderV2<static> byTree(int|string $treeId)
 * @method static QueryBuilderV2<static> root()
 * @method static QueryBuilderV2<static> parentsByModelId(int|string $modelId, ?int $level = null, bool $andSelf = false)
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

    /**
     * Get the unique identifiers for this model.
     *
     * @return array<string>
     */
    public function uniqueIds()
    {
        return [$this->getKeyName()];
    }

    /**
     * Merge tree-specific attribute casts with the model's casts.
     */
    protected function mergeTreeCasts(): void
    {
        $casts = [
            (string)$this->levelAttribute()  => 'integer',
            (string)$this->leftAttribute()   => 'integer',
            (string)$this->rightAttribute()  => 'integer',
            (string)$this->parentAttribute() => $this->getKeyType(),
        ];

        $treeAttribute = $this->treeAttribute();
        if ($treeAttribute) {
            $casts[(string)$treeAttribute] = $treeAttribute->type()->toModelCast();
        }

        $this->mergeCasts($casts);
    }

    /**
     * @throws Exception
     */
    public function getTreeBuilder(): Builder
    {
        $builder = static::buildTree();
        $builder->parent()->setType($this->resolveFieldType());

        return $builder;
    }

    private function resolveFieldType(): FieldType
    {
        $traits = class_uses_recursive($this);

        if (method_exists($this, 'usesUniqueIds') && $this->usesUniqueIds()) {
            return match (true) {
                isset($traits[HasUlids::class]) => FieldType::ULID,
                isset($traits[HasUuids::class]) => FieldType::UUID,
                default                         => FieldType::fromString($this->getKeyType()),
            };
        }

        return FieldType::fromString($this->getKeyType());
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
