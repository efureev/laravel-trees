<?php

declare(strict_types=1);

namespace Fureev\Trees;

use Fureev\Trees\Config\Helper;
use Fureev\Trees\Contracts\TreeModel;
use Illuminate\Console\BufferedConsoleOutput;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Symfony\Component\Console\Helper\Table as SymfonyTable;
use Symfony\Component\Console\Output\OutputInterface;

final class Table
{
    protected string $offset = "    ";

    protected OutputInterface $output;

    protected ?Collection $collection = null;

    protected bool $showLevel = true;

    protected string $driverClass = SymfonyTable::class;

    protected ?SymfonyTable $driver = null;

    public function draw(?OutputInterface $output = null): void
    {
        $this->setOutput($output);
        $this->render();
    }

    public function setOutput(?OutputInterface $output = null): self
    {
        $this->output = ($output ?? new BufferedConsoleOutput());

        return $this;
    }

    public function setOffset(string $offset): self
    {
        $this->offset = $offset;

        return $this;
    }

    protected array $columns = [];

    public function setExtraColumns(array $columns): self
    {
        $this->columns = $columns;

        return $this;
    }

    public function setCollection(Collection $collection): self
    {
        $this->collection = $collection;

        return $this;
    }

    public function hideLevel(): self
    {
        $this->showLevel = false;

        return $this;
    }

    public function fromQuery(QueryBuilderV2 $query): self
    {
        return $this->setCollection($query->get()->toTree());
    }


    protected function render(): void
    {
        $this->driver = instance($this->driverClass, $this->output);
        $this->driver->setHeaders($this->getColumnLabel());

        if ($this->collection) {
            $this->addRow($this->collection);
        }

        $this->driver->setFooterTitle('Total nodes: ' . $this->collection->totalCount());
        $this->driver->render();
    }

    protected function getExtraColumnLabel(): array
    {
        if (Arr::isAssoc($this->columns)) {
            return array_values($this->columns);
        }

        return $this->columns;
    }

    protected function getColumnLabel(): array
    {
        return [
            ...array_values($this->requiredColumnNames()),
            ... $this->getExtraColumnLabel(),
        ];
    }

    protected function getExtraColumnNames(): array
    {
        if (Arr::isAssoc($this->columns)) {
            return array_keys($this->columns);
        }

        return $this->columns;
    }

    protected function requiredColumnNames(): array
    {
        return array_merge($this->showLevel ? ['level' => 'Level'] : [], ['id' => 'ID']);
    }

    protected function getColumnNames(): array
    {
        static $list = [];

        if (!$list) {
            $list = $this->getExtraColumnNames();
        }

        return $list;
    }

    protected function getColumnValues(Model $node): array
    {
        $cols = $this->getColumnNames();

        return array_map(static fn($col) => $node->$col, $cols);
    }

    /**
     * @param Model&TreeModel $node
     */
    protected function buildRowData(Model $node, int $level): array
    {
        $id          = $node->getKey();
        $values      = $this->getColumnValues($node);
        $indentation = str_repeat($this->offset, $level);

        $children = $node->getRelation('children');
        if (!$children instanceof Collection) {
            $children = new Collection();
        }

        $hasChildren = !$children->isEmpty();
        $sign        = $hasChildren ? '+' : '-';

        return array_merge(
            $this->showLevel ? [$level] : [],
            [
                "$indentation $sign $id",
                ...$values,
            ]
        );
    }

    private function addRow(Collection $tree): void
    {
        /** @var Model&TreeModel $node */
        foreach ($tree as $node) {
            if (!Helper::isTreeNode($node)) {
                continue;
            }

            $level = $node->levelValue();
            $row   = $this->buildRowData($node, $level);

            $this->driver->addRow($row);

            $children = $node->getRelation('children');
            if ($children instanceof Collection && !$children->isEmpty()) {
                $this->addRow($children);
            }
        }
    }

    public static function fromModel(Model $model): self
    {
        if (!Helper::isTreeNode($model)) {
            throw new InvalidArgumentException('Model must be a node.');
        }

        return (new self())
            ->fromQuery($model->newNestedSetQuery()->descendantsQuery(null, true));
    }

    public static function fromTree(Collection $collection): self
    {
        return (new self())
            ->setCollection($collection);
    }
}
