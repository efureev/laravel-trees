<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\Unit\Contracts;

use Fureev\Trees\Contracts\TreeModel;
use Fureev\Trees\Tests\AbstractTestCase;
use Fureev\Trees\UseTree;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;

/**
 * `TreeModel` describes what a node can do. Nothing implements it — `Helper::isTreeNode()` asks
 * whether the model uses `UseTree` and tells static analysis the answer — so nothing but a test
 * can keep the description in step with the traits.
 *
 * It had fallen about two thirds behind, which meant code typed as `Model&TreeModel` could not
 * call most of a node's own API without static analysis objecting.
 */
class TreeModelContractTest extends AbstractTestCase
{
    /**
     * Methods the traits override rather than add. They belong to Eloquent, and an intersection
     * with `Model` already covers them; declaring them here would describe the framework rather
     * than the tree.
     *
     * @var string[]
     */
    private const OVERRIDES_AND_PLUMBING = [
        'getDirty',
        'newCollection',
        'newEloquentBuilder',
        'uniqueIds',
        'initializeUseTree',
        'bootUseNestedSet',
    ];

    /**
     * @return array<string, ReflectionMethod>
     */
    private function publicTraitMethods(): array
    {
        $methods = [];

        foreach ((new ReflectionClass(UseTree::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (in_array($method->getName(), self::OVERRIDES_AND_PLUMBING, true)) {
                continue;
            }

            $methods[$method->getName()] = $method;
        }

        ksort($methods);

        return $methods;
    }

    /**
     * @return array<string, ReflectionMethod>
     */
    private function contractMethods(): array
    {
        $methods = [];

        foreach ((new ReflectionClass(TreeModel::class))->getMethods() as $method) {
            $methods[$method->getName()] = $method;
        }

        ksort($methods);

        return $methods;
    }

    private function describe(ReflectionMethod $method): string
    {
        $declaring  = $method->getDeclaringClass()->getName();
        $parameters = [];

        foreach ($method->getParameters() as $parameter) {
            $parameters[] = $this->typeName($parameter->getType(), $declaring) . ' $' . $parameter->getName()
                . ($parameter->isDefaultValueAvailable() ? ' = ...' : '');
        }

        return sprintf(
            '%s(%s): %s',
            $method->getName(),
            implode(', ', $parameters),
            $this->typeName($method->getReturnType(), $declaring)
        );
    }

    private function typeName(?ReflectionType $type, string $declaring): string
    {
        if ($type === null) {
            return 'mixed';
        }

        $name = $type instanceof ReflectionNamedType ? $type->getName() : (string)$type;

        // `self` is written on both sides and resolves differently: to the using class in a
        // trait, to the interface in an interface. PHP 8.4 reported the word back as written and
        // 8.5 resolves it, so the declaring class is folded back to `self` to compare what was
        // declared rather than what each side resolves it to.
        if ($name === $declaring) {
            $name = 'self';
        }

        return ($type->allowsNull() && $name !== 'mixed' && !str_contains($name, 'null') ? '?' : '') . $name;
    }

    public function testEveryPublicTraitMethodIsDeclared(): void
    {
        $missing = array_diff(
            array_keys($this->publicTraitMethods()),
            array_keys($this->contractMethods())
        );

        static::assertSame([], array_values($missing));
    }

    public function testTheContractDeclaresNothingTheTraitsDoNotProvide(): void
    {
        $stray = array_diff(
            array_keys($this->contractMethods()),
            array_keys($this->publicTraitMethods()),
        );

        // These come from the query builder and are reached through Eloquent's forwarding, which
        // is exactly the kind of thing a phpstan-only contract exists to spell out.
        static::assertSame(
            [
                'getQuery',
                'wrappedColumns',
                'wrappedKey',
                'wrappedTable',
            ],
            array_values($stray)
        );
    }

    public function testTheSignaturesMatch(): void
    {
        $contract = $this->contractMethods();

        foreach ($this->publicTraitMethods() as $name => $method) {
            if (!isset($contract[$name])) {
                continue;
            }

            static::assertSame(
                $this->describe($method),
                $this->describe($contract[$name]),
                "TreeModel::$name() has drifted from the trait"
            );
        }
    }
}
