<?php

namespace Fureev\Trees;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;

class NestedSetConfig
{
    public const OPERATION_MAKE_ROOT = 1;
    public const OPERATION_PREPEND_TO = 2;
    public const OPERATION_APPEND_TO = 3;
    public const OPERATION_INSERT_BEFORE = 4;
    public const OPERATION_INSERT_AFTER = 5;
    public const OPERATION_DELETE_ALL = 6;

    /**
     * @var string
     */
    public $leftAttribute = 'lft';

    /**
     * @var string
     */
    public $rightAttribute = 'rgt';

    /**
     * @var string
     */
    public $levelAttribute = 'lvl';

    /**
     * @var string
     */
    public $parentAttribute = 'parent_id';

    /**
     * @var Model
     */
    protected $node;

    /**
     * Add default nested set columns to the table. Also create an index.
     *
     * @param \Illuminate\Database\Schema\Blueprint $table
     */
    public function columns(Blueprint $table): void
    {
        $table->unsignedInteger($this->leftAttribute)->default(0);
        $table->unsignedInteger($this->rightAttribute)->default(0);
        $table->unsignedInteger($this->parentAttribute)->nullable();
        $table->integer($this->levelAttribute);
        $table->index(static::getDefaultColumns());

        $table->index([$this->leftAttribute, $this->rightAttribute], $table->getTable() . "_{$this->leftAttribute}_idx");
        $table->index([$this->rightAttribute], $table->getTable() . "_{$this->rightAttribute}_idx");
        $table->index([$this->parentAttribute], $table->getTable() . "_{$this->parentAttribute}_idx");
    }

    /**
     * @param \Illuminate\Database\Schema\Blueprint $table
     */
    public static function getColumns(Blueprint $table)
    {
        return (new static())->columns($table);
    }

    /**
     * Drop NestedSet columns.
     *
     * @param \Illuminate\Database\Schema\Blueprint $table
     */
    public static function dropColumns(Blueprint $table): void
    {
        $columns = (new static())->getDefaultColumns();
        $table->dropIndex($columns);
        $table->dropColumn($columns);
    }

    /**
     * Get a list of default columns.
     *
     * @return array
     */
    public function getDefaultColumns(): array
    {
        return [$this->leftAttribute, $this->rightAttribute, $this->levelAttribute, $this->parentAttribute];
    }

}
