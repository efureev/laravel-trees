<?php

declare(strict_types=1);

namespace Fureev\Trees\Database;

use Fureev\Trees\Config\Builder;
use Fureev\Trees\UseTree;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Php\Support\Exceptions\InvalidConfigException;

class Migrate
{
    public function __construct(protected Builder $builder, protected Blueprint $table)
    {
    }

    /**
     * @param Blueprint $table
     * @param string|Model $model
     *
     * @throws InvalidConfigException
     */
    public static function columnsFromModel(Blueprint $table, Model|string $model): void
    {
        /** @var Model|UseTree $instance */
        $instance = instance($model);

        if (method_exists($instance, 'getTreeBuilder')) {
            (new static($instance->getTreeBuilder(), $table))->buildColumns();
            return;
        }

        throw new InvalidConfigException();
    }

    /**
     * Add default nested set columns to the table. Also create an index.
     */
    public function buildColumns(): void
    {
        foreach ($this->builder->columnsList() as $attribute) {
            $this->table->{$attribute->type()->value}($attribute->columnName())
                ->default($attribute->default())
                ->nullable($attribute->nullable());
        }

        $this->buildIndexes();
    }

    private function buildIndexes(): void
    {
        foreach ($this->builder->columnIndexes() as $idx => $columns) {
            $this->buildIndex($idx, (array)$columns);
        }
    }

    private function buildIndex(string $name, array $columns): void
    {
        $cols = [];
        //        if ($this->config->tree()) {
        //            $cols[] = $this->config->tree()->name();
        //        }

        $cols = array_merge($cols, $columns);

        $this->table->index(
            $cols,
            $this->table->getTable() . "_{$name}_idx"
        );
    }

    /**
     * Drop NestedSet columns.
     */
    public function dropColumns(): void
    {
        foreach ($this->builder->columnIndexes() as $idx => $columns) {
            $this->table->dropIndex($idx);
        }

        foreach ($this->builder->columnsNames() as $column) {
            $this->table->dropColumn($column);
        }
    }
}
