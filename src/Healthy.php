<?php

namespace Fureev\Trees;

use Illuminate\Database\Query\Builder;

trait Healthy
{
    /**
     * Get statistics of errors of the tree.
     *
     * @return array
     */
    public function countErrors(): array
    {
        $checks = [];

        // Check if lft and rgt values are ok
        $checks['oddness'] = $this->getOddnessQuery();

        // Check if lft and rgt values are unique
        $checks['duplicates'] = $this->getDuplicatesQuery();

        // Check if parent_id is set correctly
        $checks['wrong_parent'] = $this->getWrongParentQuery();

        // Check for nodes that have missing parent
        $checks['missing_parent'] = $this->getMissingParentQuery();

        $query = $this->query->newQuery();

        foreach ($checks as $key => $inner) {
            $inner->selectRaw('count(1)');

            $query->selectSub($inner, $key);
        }

        return (array)$query->first();
    }


    protected function getOddnessQuery(): Builder
    {
        return $this->model
            ->newNestedSetQuery()
            ->toBase()
            ->whereNested(function (Builder $inner) {
                [$lft, $rgt] = $this->wrappedColumns();

                $inner
                    ->whereRaw("$lft >= $rgt")
                    ->orWhereRaw("($rgt - $lft) % 2 = 0");
            });
    }


    protected function getDuplicatesQuery(): Builder
    {
        $table   = $this->wrappedTable();
        $keyName = $this->wrappedKey();

        $firstAlias  = 'c1';
        $secondAlias = 'c2';

        $waFirst  = $this->query->getGrammar()->wrapTable($firstAlias);
        $waSecond = $this->query->getGrammar()->wrapTable($secondAlias);

        $query = $this->model
            ->newNestedSetQuery($firstAlias)
            ->toBase()
            ->from($this->query->raw("{$table} as {$waFirst}, {$table} {$waSecond}"))
            ->whereRaw("{$waFirst}.{$keyName} < {$waSecond}.{$keyName}")
            ->whereNested(function (Builder $inner) use ($waFirst, $waSecond) {
                [$lft, $rgt] = $this->wrappedColumns();

                $inner->orWhereRaw("{$waFirst}.{$lft}={$waSecond}.{$lft}")
                    ->orWhereRaw("{$waFirst}.{$rgt}={$waSecond}.{$rgt}")
                    ->orWhereRaw("{$waFirst}.{$lft}={$waSecond}.{$rgt}")
                    ->orWhereRaw("{$waFirst}.{$rgt}={$waSecond}.{$lft}");
            });

        return $this->model->applyNestedSetScope($query, $secondAlias);
    }


    protected function getWrongParentQuery(): Builder
    {
        $table   = $this->wrappedTable();
        $keyName = $this->wrappedKey();

        $grammar = $this->query->getGrammar();

        $parentIdName = $grammar->wrap($this->model->parentAttribute()->name());

        $parentAlias = 'p';
        $childAlias  = 'c';
        $intermAlias = 'i';

        $waParent = $grammar->wrapTable($parentAlias);
        $waChild  = $grammar->wrapTable($childAlias);
        $waInterm = $grammar->wrapTable($intermAlias);

        $query = $this->model
            ->newNestedSetQuery('c')
            ->toBase()
            ->from($this->query->raw("{$table} as {$waChild}, {$table} as {$waParent}, $table as {$waInterm}"))
            ->whereRaw("{$waChild}.{$parentIdName}={$waParent}.{$keyName}")
            ->whereRaw("{$waInterm}.{$keyName} <> {$waParent}.{$keyName}")
            ->whereRaw("{$waInterm}.{$keyName} <> {$waChild}.{$keyName}")
            ->whereNested(function (Builder $inner) use ($waInterm, $waChild, $waParent) {
                [$lft, $rgt] = $this->wrappedColumns();

                $inner->whereRaw("{$waChild}.{$lft} not between {$waParent}.{$lft} and {$waParent}.{$rgt}")
                    ->orWhereRaw("{$waChild}.{$lft} between {$waInterm}.{$lft} and {$waInterm}.{$rgt}")
                    ->whereRaw("{$waInterm}.{$lft} between {$waParent}.{$lft} and {$waParent}.{$rgt}");
            });

        $this->model->applyNestedSetScope($query, $parentAlias);
        $this->model->applyNestedSetScope($query, $intermAlias);

        return $query;
    }


    protected function getMissingParentQuery(): Builder
    {
        return $this->model
            ->newNestedSetQuery()
            ->toBase()
            ->whereNested(function (Builder $inner) {
                $grammar = $this->query->getGrammar();

                $table        = $this->wrappedTable();
                $keyName      = $this->wrappedKey();
                $parentIdName = $grammar->wrap($this->model->parentAttribute()->name());
                $alias        = 'p';
                $wrappedAlias = $grammar->wrapTable($alias);

                $existsCheck = $this->model
                    ->newNestedSetQuery()
                    ->toBase()
                    ->selectRaw('1')
                    ->from($this->query->raw("{$table} as {$wrappedAlias}"))
                    ->whereRaw("{$table}.{$parentIdName} = {$wrappedAlias}.{$keyName}")
                    ->limit(1);

                $this->model->applyNestedSetScope($existsCheck, $alias);

                $inner->whereRaw("{$parentIdName} is not null")
                    ->addWhereExistsQuery($existsCheck, 'and', true);
            });
    }

    /**
     * Get the number of total errors of the tree.
     *
     * @return int
     */
    public function getTotalErrors(): int
    {
        return (int)array_sum($this->countErrors());
    }

    /**
     * Get whether the tree is broken.
     *
     * @return bool
     */
    public function isBroken(): bool
    {
        return $this->getTotalErrors() > 0;
    }
}
