<?php

declare(strict_types=1);

namespace Fureev\Trees\Healthy;

use Illuminate\Database\Eloquent\Model;

final readonly class HealthyChecker
{
    private Model $model;
    private array $checkers;

    public function __construct(Model|string $model)
    {
        if ($model instanceof Model) {
            $model = $model::class;
        }

        $this->model = instance($model);

        $this->checkers = [
            OddnessCheck::class,
            DuplicatesCheck::class,
            WrongParentCheck::class,
//            MissingParentCheck::class,
        ];
    }

    private function checkOne(string $checker): int
    {
        /** @var AbstractCheck $checker */
        $checker = instance($checker, $this->model);

        return $checker->check();
    }

    /**
     * Get statistics of errors of the tree.
     */
    public function check(): array
    {
        $checks = [];

        foreach ($this->checkers as $checker) {
            $checks[class_basename($checker)] = $this->checkOne($checker);
        }

        return $checks;
    }

    /**
     * Get the number of total errors of the tree.
     */
    public function getTotalErrors(): int
    {
        return (int)array_sum($this->check());
    }

    /**
     * Get whether the tree is broken.
     */
    public function isBroken(): bool
    {
        return $this->getTotalErrors() > 0;
    }
}
