<?php

namespace Fureev\Trees;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;

/**
 * Class BaseRelation
 * @package Fureev\Trees
 *
 * @property QueryBuilder $query
 * @property NestedSetTrait|Model
 */
abstract class BaseRelation extends Relation
{
    /**
     * AncestorsRelation constructor.
     *
     * @param QueryBuilder $builder
     * @param Model $parent
     */
    public function __construct(QueryBuilder $builder, Model $parent)
    {
        if (!Config::isNode($parent)) {
            throw new \InvalidArgumentException('Model must be node.');
        }
        parent::__construct($builder, $parent);
    }

    /**
     * @param Model $model
     * @param $related
     *
     * @return bool
     */
    abstract protected function matches(Model $model, $related): bool;

    /**
     * @param QueryBuilder $query
     * @param Model $model
     *
     * @return void
     */
    abstract protected function addEagerConstraint($query, $model): void;

    /**
     * @param $hash
     * @param $table
     * @param $lft
     * @param $rgt
     *
     * @return string
     */
    abstract protected function relationExistenceCondition($hash, $table, $lft, $rgt): string;

    /**
     * Initialize the relation on a set of models.
     *
     * @param array $models
     * @param string $relation
     *
     * @return array
     */
    public function initRelation(array $models, $relation): array
    {
        return $models;
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
        return $this->query->get();
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param array $models
     *
     * @return void
     */
    public function addEagerConstraints(array $models): void
    {
        // The first model in the array is always the parent, so add the scope constraints based on that model.
        // @link https://github.com/laravel/framework/pull/25240
        // @link https://github.com/lazychaser/laravel-nestedset/issues/351
        optional($models[0])->applyNestedSetScope($this->query);
        $this->query->whereNested(
            function (Builder $inner) use ($models) {
            // We will use this query in order to apply constraints to the
            // base query builder
                $outer = $this->parent->newQuery()->setQuery($inner);
                foreach ($models as $model) {
                    $this->addEagerConstraint($outer, $model);
                }
            }
        );
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param array $models
     * @param EloquentCollection $results
     * @param string $relation
     *
     * @return array
     */
    public function match(array $models, EloquentCollection $results, $relation)
    {
        foreach ($models as $model) {
            $related = $this->matchForModel($model, $results);
            $model->setRelation($relation, $related);
        }
        return $models;
    }

    /**
     * @param Model $model
     * @param EloquentCollection $results
     *
     * @return EloquentCollection
     */
    protected function matchForModel(Model $model, EloquentCollection $results)
    {
        $result = $this->related->newCollection();
        foreach ($results as $related) {
            if ($this->matches($model, $related)) {
                $result->push($related);
            }
        }

        return $result;
    }

    public function getRelationQuery(EloquentBuilder $query, EloquentBuilder $parent, $columns = ['*'])
    {
        dd(debug_print_backtrace(), 'stop here: ' . __FUNCTION__);
    }
}
