<?php

namespace Fureev\Trees\Tests\Functional\Helpers;

use Fureev\Trees\Tests\Database\Factories\SoftDeleteStructureFactory;
use Fureev\Trees\Tests\Database\Factories\StructureFactory;
use Fureev\Trees\Tests\models\SoftDeleteStructure;
use Fureev\Trees\Tests\models\Structure;
use Ramsey\Uuid\Uuid;

trait StructureHelper
{
    public function createStructure(Structure $parent = null, array $attributes = []): Structure
    {
        return $this->createCustomStructure(StructureFactory::class, $parent, $attributes);
    }

    public function createSoftDeleteStructure(
        SoftDeleteStructure $parent = null,
        array $attributes = [],
        string $method = 'appendTo'
    ): SoftDeleteStructure {
        return $this->createCustomStructure(SoftDeleteStructureFactory::class, $parent, $attributes, $method);
    }

    /**
     * @param string|\Illuminate\Database\Eloquent\Factories\Factory $classFactory
     * @param Structure|null $parent
     * @param array $attributes
     *
     * @return Structure|SoftDeleteStructure
     */
    public function createCustomStructure(
        string $classFactory,
        Structure $parent = null,
        array $attributes = [],
        string $method = 'appendTo'
    ): Structure|SoftDeleteStructure {
        $treeId = $parent ? $parent->treeValue() : Uuid::uuid4()->toString();

        /** @var Structure $model */
        $model = $classFactory::new()->make($attributes)->setTree($treeId);

        if ($parent) {
            $model->{$method}($parent)->save();
        }

        return tap($model, static fn($model) => $model->save());
    }

    private static function refreshModels(Structure ...$models): void
    {
        foreach ($models as $model) {
            $model->refresh();
        }
    }
}
