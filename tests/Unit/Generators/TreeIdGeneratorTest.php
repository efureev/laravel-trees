<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Unit\Generators;

use Fureev\Trees\Config\Attribute;
use Fureev\Trees\Config\AttributeType;
use Fureev\Trees\Config\FieldType;
use Fureev\Trees\Generators\TreeIdGenerator;
use Fureev\Trees\Tests\Unit\AbstractUnitTestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Mockery;

class TreeIdGeneratorTest extends AbstractUnitTestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGenerateIntegerId(): void
    {
        $attribute = Attribute::make(AttributeType::Tree, FieldType::UnsignedInteger);
        $generator = new TreeIdGenerator($attribute);

        $model = Mockery::mock(Model::class);

        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('max')->with('tree_id')->andReturn(5);

        $model->shouldReceive('newQuery')->andReturn($query);

        $result = $generator->generateId($model);

        $this->assertEquals(6, $result);
        $this->assertIsInt($result);
    }

    public function testGenerateUuid(): void
    {
        $attribute = Attribute::make(AttributeType::Tree, FieldType::UUID);
        $generator = new TreeIdGenerator($attribute);

        $model  = Mockery::mock(Model::class);
        $result = $generator->generateId($model);

        $this->assertIsString($result);
        $this->assertTrue(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $result) === 1);
    }

    public function testGenerateUlid(): void
    {
        $attribute = Attribute::make(AttributeType::Tree, FieldType::ULID);
        $generator = new TreeIdGenerator($attribute);

        $model  = Mockery::mock(Model::class);
        $result = $generator->generateId($model);

        $this->assertIsString($result);
        $this->assertEquals(26, strlen($result));
    }
}
