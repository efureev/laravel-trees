<?php

namespace Fureev\Trees\Tests\Database\Factories;

use Fureev\Trees\Tests\models\Structure;
use Illuminate\Database\Eloquent\Factories\Factory;

class StructureFactory extends Factory
{
    protected $model = Structure::class;

    public function definition(): array
    {
        return [
            'title'  => implode(' ', $this->faker->words(3)),
            'params' => [],
            'path'   => [],
        ];
    }
}
