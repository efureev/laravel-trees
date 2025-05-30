<?php

declare(strict_types=1);

namespace Fureev\Trees\Database;

use Fureev\Trees\Config\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Php\Support\Exceptions\InvalidConfigException;

final readonly class Migrate
{
    public function __construct(
        protected Builder $builder,
        protected Blueprint $table
    ) {
    }

    /**
     * @throws InvalidConfigException
     */
    public static function columnsFromModel(Blueprint $table, Model|string $model): Builder
    {
        $instance = is_string($model) ? new $model() : $model;

        if (!method_exists($instance, 'getTreeBuilder')) {
            throw new InvalidConfigException([], 'Model does not implement tree structure');
        }

        $builder = $instance->getTreeBuilder();
        (new self($builder, $table))->buildColumns();

        return $builder;
    }

    /**
     * Add default nested set columns to the table. Also create an index.
     */
    public function buildColumns(): void
    {
        $this->addTreeColumns();
        $this->buildIndexes();
    }

    /**
     * Adds tree structure columns to the table.
     */
    private function addTreeColumns(): void
    {
        foreach ($this->builder->columnsList() as $attribute) {
            $this->table->{$attribute->type()->value}($attribute->columnName())
                ->default($attribute->default())
                ->nullable($attribute->nullable());
        }
    }


    private function buildIndexes(): void
    {
        foreach ($this->builder->columnIndexes() as $indexName => $columns) {
            $this->createIndex($indexName, (array)$columns);
        }
    }

    /**
     * Creates a single index, adding tree column for multi-tree structures.
     *
     * @param string $indexName Base name for the index
     * @param array $columns Columns to include in the index
     */
    private function createIndex(string $indexName, array $columns): void
    {
        if ($this->builder->isMulti()) {
            $columns[] = $this->builder->tree()->columnName();
        }

        $indexFullName = $this->table->getTable() . "_{$indexName}_idx";
        $this->table->index($columns, $indexFullName);
    }


    /**
     * Drops all nested set columns and their indexes.
     */
    public function dropColumns(): void
    {
        $this->dropTreeIndexes();
        $this->dropTreeColumns();
    }

    /**
     * Drops all tree structure indexes.
     */
    private function dropTreeIndexes(): void
    {
        foreach ($this->builder->columnIndexes() as $indexName => $columns) {
            $this->table->dropIndex($indexName);
        }
    }

    /**
     * Drops all tree structure columns.
     */
    private function dropTreeColumns(): void
    {
        foreach ($this->builder->columnsNames() as $column) {
            $this->table->dropColumn($column);
        }
    }
}
