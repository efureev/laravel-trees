<?php

declare(strict_types=1);

namespace Fureev\Trees\Healthy;

use Fureev\Trees\Config\Helper;
use Illuminate\Database\Eloquent\Model;

final readonly class HealthyChecker
{
    private Model $model;
    private array $checkers;

    /**
     * Takes the model to check, or the name of its class.
     *
     * A model handed over is kept as it is — see AbstractCheck for why reducing it to a class
     * name threw away the connection and the scope values it carried.
     */
    public function __construct(Model|string $model)
    {
        $model = $model instanceof Model ? $model : Helper::instance($model);

        $this->model = $model;

        $this->checkers = [
            OddnessCheck::class,
            DuplicatesCheck::class,
            WrongParentCheck::class,
            MissingParentCheck::class,
            RangeCheck::class,
            RootCheck::class,
        ];
    }

    private function checkOne(string $checker): int
    {
        /** @var AbstractCheck $checker */
        $checker = Helper::instance($checker, $this->model);

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
