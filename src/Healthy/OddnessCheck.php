<?php

declare(strict_types=1);

namespace Fureev\Trees\Healthy;

use Fureev\Trees\QueryBuilderV2;
use Illuminate\Database\Query\Builder;

final readonly class OddnessCheck extends AbstractCheck
{
    protected function query(): Builder
    {
        /** @var QueryBuilderV2 $builder */
        $builder = $this->model->newNestedSetQuery();

        return $builder
            ->toBase()
            ->whereNested(
                function (Builder $inner) use ($builder) {
                    [
                        $lft,
                        $rgt,
                    ] = $builder->wrappedColumns();

                    $inner
                        ->whereRaw("$lft >= $rgt")
                        ->orWhereRaw("($rgt - $lft) % 2 = 0");
                }
            );
    }
}
