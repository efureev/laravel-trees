![PHP Laravel Package](https://github.com/efureev/laravel-trees/workflows/PHP%20Laravel%20Package/badge.svg?branch=master)
![](https://img.shields.io/badge/php-8.2|8.3-blue.svg)
![](https://img.shields.io/badge/Laravel-^11.*-red.svg)
[![Total Downloads](https://poser.pugx.org/efureev/laravel-trees/downloads)](https://packagist.org/packages/efureev/laravel-trees)
[![License](https://poser.pugx.org/efureev/laravel-trees/license)](https://packagist.org/packages/efureev/laravel-trees)
[![composer.lock available](https://poser.pugx.org/efureev/laravel-trees/composerlock)](https://packagist.org/packages/efureev/laravel-trees)

[![Latest Stable Version](https://poser.pugx.org/efureev/laravel-trees/version)](https://packagist.org/packages/efureev/laravel-trees)

[![Maintainability](https://api.codeclimate.com/v1/badges/69eff0098adbf728341d/maintainability)](https://codeclimate.com/github/efureev/laravel-trees/maintainability)
[![Test Coverage](https://api.codeclimate.com/v1/badges/69eff0098adbf728341d/test_coverage)](https://codeclimate.com/github/efureev/laravel-trees/test_coverage)

# Laravel Tree Structure

__Contents:__

- [Theory](#information)
- [Installation](#installation)
- [Documentation](#documentation)
  - [Basic](docs/Basic.md)
  - [AdvancedTreeConfig](docs/AdvancedTreeConfig.md)
  - [Migration](docs/Migration.md)
  - [Creating Nodes](docs/CreatingNodes.md)
  - [Managing Nodes](docs/ManagingNodes.md)
  - [Receiving Nodes](docs/ReceivingNodes.md)
  - [Model's Helpers](docs/Helpers.md)
  - [Console](docs/Console.md)
  - [Health And Fixing.md](docs/HealthAndFix.md)

## Information

This package is Multi-Tree structures (a lot of root-nodes).

### Screenshots

<div>
  <img src="./docs/assets/tree.png" alt="html tree" style="max-width: 400px" />
  <img src="./docs/assets/table.png" alt="console tree" style="max-width: 800px" />
</div>

### What are nested sets?

Nested sets or [Nested Set Model](http://en.wikipedia.org/wiki/Nested_set_model) is a way to effectively store
hierarchical data in a relational table. From wikipedia:

> The nested set model is to number the nodes according to a tree traversal,
> which visits each node twice, assigning numbers in the order of visiting, and
> at both visits. This leaves two numbers for each node, which are stored as two
> attributes. Querying becomes inexpensive: hierarchy membership can be tested by
> comparing these numbers. Updating requires renumbering and is therefore expensive.

### Applications

NSM shows good performance when tree is updated rarely. It is tuned to be fast for getting related nodes. It is ideally
suited for building multi-depth menu or categories for shop.

## Requirements

- PHP: 8.2|8.3
- Laravel: ^11.*

It is highly suggested to use database that supports transactions (like Postgres) to secure a tree from possible
corruption.

## Installation

```shell
composer require efureev/laravel-trees
```

## Testing

```shell
./vendor/bin/phpunit --testdox
# or
composer test
```

## Documentation

The package allows to create multi-root structures: no only-one-root! And allows to move nodes between trees.  
Moreover, it also works with different model's primary key: `int`, `uuid`, `ulid`.

- [Basic](docs/Basic.md)
- [AdvancedTreeConfig](docs/AdvancedTreeConfig.md)
- [Migration](docs/Migration.md)
- [Creating Nodes](docs/CreatingNodes.md)
- [Managing Nodes](docs/ManagingNodes.md)
- [Receiving Nodes](docs/ReceivingNodes.md)
- [Model's Helpers](docs/Helpers.md)
- [Console](docs/Console.md)
- [Health And Fixing.md](docs/HealthAndFix.md)
