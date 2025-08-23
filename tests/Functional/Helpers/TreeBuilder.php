<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Functional\Helpers;

use Fureev\Trees\Tests\models\v5\AbstractModel;

final class TreeBuilder
{
    public function __construct(private AbstractModel $parentNode)
    {
    }

    public static function from(string $model, string $title = 'Root Node'): self
    {
        $model = instance($model, ['title' => $title]);
        $model->makeRoot()->save();

        return new self($model);
    }

    public function build(int ...$childrenNodesCount): AbstractModel
    {
        if (!count($childrenNodesCount)) {
            return $this->parentNode;
        }

        $this->buildChildren($childrenNodesCount);

        return $this->parentNode->refresh();
    }

    public function buildChildren(array $childrenNodesCount): void
    {
        $childrenCount = array_shift($childrenNodesCount);

        for ($i = 1; $i <= $childrenCount; $i++) {
            $node = $this->makeNode($i);
            $node->save();

            (new TreeBuilder($node))->build(...$childrenNodesCount);
        }
    }

    private function makeNode(int $lvl): AbstractModel
    {
        $path    = $this->parentNode->path;
        $path[]  = $lvl;
        $pathStr = implode('.', $path);

        $node       = instance($this->parentNode::class, ['title' => "child $pathStr"]);
        $node->path = $path;

        $node->prependTo($this->parentNode);

        return $node;
    }
}
