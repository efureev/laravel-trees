<?php

namespace Fureev\Trees;

use Fureev\Trees\Contracts\NestedSetConfig;
use Fureev\Trees\Contracts\TreeConfigurable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Php\Support\Exceptions\InvalidConfigException;

/**
 * Class Migrate
 * @package Fureev\Trees
 */
class Migrate
{
    /** @var NestedSetConfig */
    protected $config;

    public function __construct(NestedSetConfig $config)
    {
        $this->config = $config;
    }

    /**
     * @param Blueprint $table
     * @param NestedSetConfig $config
     */
    public static function getColumns(Blueprint $table, NestedSetConfig $config): void
    {
        (new static($config))->columns($table);
    }

    /**
     * @param Blueprint $table
     * @param string|Model $model
     *
     * @throws InvalidConfigException
     */
    public static function getColumnsFromModel(Blueprint $table, $model): void
    {
        if (is_string($model)) {
            $model = new $model();
        }

        if ($model instanceof TreeConfigurable) {
            static::getColumns($table, $model->getTreeConfig());
            return;
        }

        throw new InvalidConfigException();
    }

    /**
     * Add default nested set columns to the table. Also create an index.
     *
     * @param Blueprint $table
     */
    public function columns(Blueprint $table): void
    {
        if ($this->config->isMultiTree()) {
            $table->{$this->config->getTreeAttributeType()}($this->config->getTreeAttributeName());
        }

        $table->unsignedInteger($this->config->getLeftAttributeName())->default(0);
        $table->unsignedInteger($this->config->getRightAttributeName())->default(0);

        $table->{$this->config->getParentAttributeType()}($this->config->getParentAttributeName())->nullable();

        $table->integer($this->config->getLevelAttributeName());
        // @todo: need next index ??
        //        $table->index($this->getDefaultColumns());

        if ($this->config->isMultiTree()) {
            $table->index(
                [$this->config->getTreeAttributeName()],
                $table->getTable() . "_{$this->config->getTreeAttributeName()}_idx"
            );
            $table->index(
                [
                    $this->config->getTreeAttributeName(),
                    $this->config->getLeftAttributeName(),
                    $this->config->getRightAttributeName(),
                ],
                $table->getTable() . "_{$this->config->getLeftAttributeName()}_idx"
            );
            $table->index(
                [
                    $this->config->getTreeAttributeName(),
                    $this->config->getRightAttributeName(),
                ],
                $table->getTable() . "_{$this->config->getRightAttributeName()}_idx"
            );
            $table->index(
                [
                    $this->config->getTreeAttributeName(),
                    $this->config->getParentAttributeName(),
                ],
                $table->getTable() . "_{$this->config->getParentAttributeName()}_idx"
            );
        } else {
            $table->index(
                [
                    $this->config->getLeftAttributeName(),
                    $this->config->getRightAttributeName(),
                ],
                $table->getTable() . "_{$this->config->getLeftAttributeName()}_idx"
            );
            $table->index(
                [$this->config->getRightAttributeName()],
                $table->getTable() . "_{$this->config->getRightAttributeName()}_idx"
            );
            $table->index(
                [$this->config->getParentAttributeName()],
                $table->getTable() . "_{$this->config->getParentAttributeName()}_idx"
            );
        }
    }

    /**
     * Drop NestedSet columns.
     *
     * @param Blueprint $table
     * @param NestedSetConfig $config
     */
    public static function dropColumns(Blueprint $table, NestedSetConfig $config): void
    {
        $columns = $config->columns();

        $table->dropIndex($columns);
        $table->dropColumn($columns);
    }
}
