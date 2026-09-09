<?php

declare(strict_types=1);

namespace Fureev\Trees\Config;

use Fureev\Trees\Contracts\TreeModel;
use Fureev\Trees\UseTree;
use Illuminate\Database\Eloquent\Model;

final readonly class Helper
{
    /**
     * @phpstan-assert-if-true Model&TreeModel $model
     */
    public static function isTreeNode(mixed $model): bool
    {
        return $model instanceof Model && (class_uses_recursive($model)[UseTree::class] ?? null);
    }

    /**
     * Build an object from a class name, or pass one through untouched.
     *
     * The package used to reach for a global `instance()` out of a support package it borrowed
     * three small things from. This is the whole of what it used.
     *
     * @template T of object
     * @phpstan-param T|class-string<T>|null $instance
     * @phpstan-return T|null
     */
    public static function instance(string|object|null $instance, mixed ...$params): ?object
    {
        if (is_object($instance)) {
            return $instance;
        }

        if (is_string($instance) && class_exists($instance)) {
            return new $instance(...$params);
        }

        return null;
    }

    public static function isModelSoftDeletable(Model|string $model): bool
    {
        return method_exists($model instanceof Model ? $model::class : $model, 'bootSoftDeletes');
    }
}
