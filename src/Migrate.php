<?php

namespace Fureev\Trees;

use Fureev\Trees\Config\AbstractAttribute;
use Fureev\Trees\Config\Base;
use Fureev\Trees\Contracts\NestedSetConfig;
use Fureev\Trees\Contracts\TreeConfigurable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Php\Support\Exceptions\InvalidConfigException;

/**
 * Class Migrate
 * @package Fureev\Trees
 *
 * @mixin Base
 */
class Migrate
{
    /**
     * @var NestedSetConfig
     */
    protected NestedSetConfig $config;

    /**
     * @var Blueprint
     */
    protected Blueprint $table;

    public function __construct(Blueprint $table, NestedSetConfig $config)
    {
        $this->table  = $table;
        $this->config = $config;
    }

    /**
     * @param  Blueprint  $table
     * @param  NestedSetConfig  $config
     */
    public static function columns(Blueprint $table, NestedSetConfig $config): void
    {
        (new static($table, $config))->buildColumns();
    }

    /**
     * @param  Blueprint  $table
     * @param  string|Model  $model
     *
     * @throws InvalidConfigException
     */
    public static function columnsFromModel(Blueprint $table, Model|string $model): void
    {
        /** @var Model $m */
        $m = instance($model);

        if ($m instanceof TreeConfigurable || method_exists($m, 'getTreeConfig')) {
            static::columns($table, $m->getTreeConfig());
            return;
        }

        throw new InvalidConfigException();
    }

    /**
     * Add default nested set columns to the table. Also create an index.
     */
    public function buildColumns(): void
    {
        /** @var AbstractAttribute $column */
        foreach ($this->config->columns(false) as $column) {
            $this->table->{$column->type()}($column->name())
                ->default($column->default())
                ->nullable($column->nullable());
        }

        $this->buildIndexes();
    }

    private function buildIndexes(): void
    {
        foreach ($this->config->indexes() as $idx => $columns) {
            $this->buildIndex($idx, $columns);
        }
    }

    private function buildIndex($name, $column): void
    {
        $cols = [];
        if ($this->config->tree()) {
            $cols[] = $this->config->tree()->name();
        }

        $cols = array_merge($cols, (array) $column);

        $this->table->index(
            $cols,
            $this->table->getTable()."_{$name}_idx"
        );
    }

    /**
     * Drop NestedSet columns.
     */
    public function dropColumns(): void
    {
        foreach ($this->config->indexes() as $idx => $columns) {
            $this->table->dropIndex($idx);
        }

        foreach ($this->config->columns() as $column) {
            $this->table->dropColumn($column);
        }
    }

    public function __call($method, $arguments)
    {
        return $this->config->$method(...$arguments);
    }
}
