<?php

declare(strict_types=1);

namespace Fureev\Trees\Database;

use Fureev\Trees\Config\Builder;
use Fureev\Trees\Exceptions\Exception;
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
            $type = $attribute->type()->value;
            if (method_exists($this->table, $type)) {
                $this->table->$type($attribute->columnName())
                    ->default($attribute->default())
                    ->nullable($attribute->nullable());
            } else {
                throw new Exception('Blueprint type "' . $type . '" does not exist.');
            }
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
        if ($this->builder->tree() !== null) {
            $cols[] = (string)$this->builder->tree();
        }

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
