# Changelog

## [unreleased]

### Added

- Composer script `gate` running PHPStan, PHPCS and PHPUnit in the order CI uses them, plus
  `gate:docker` and a matching `gate` service in `docker-compose.yml`, so the whole CI gate can be
  reproduced locally with one command
- Regression coverage for `QueryBuilder\Fixing::makeGap()` called with a zero offset

### Changed

- CI job `lint` renamed to `Static Analysis & Coding Standards` and now runs `composer phpcs`
  next to `composer phpstan`, so the coding standard is enforced instead of merely declared
- `.phpcs.xml` no longer enforces member variable naming: Eloquent exposes database columns as
  snake_case properties (`$model->parent_id`, `$model->tree_id`), which are schema rather than style
- `.phpcs.xml` no longer requires a docblock on every property, joining the other
  `Squiz.Commenting.*` sniffs already disabled there
- `BaseRelation::relationExistenceCondition()` replaced by `addExistenceConstraint()`, which
  applies `whereColumn()` constraints instead of returning a raw SQL fragment. The method is
  protected and had no callers
- `DeleteWithChildren` asks for the trashed rows explicitly on a hard delete instead of relying
  on `Builder::forceDelete()` running on the raw query and skipping global scopes. Behaviour is
  unchanged, the dependency on a Laravel implementation detail is not
- `QueryBuilder\Fixing::makeGap()` declares `int` parameter types. No working call changes
  behaviour: any non-integer argument already produced invalid SQL rather than a query
- `.phpcs.xml` drops `Squiz.ControlStructures.ElseIfDeclaration`: it demands `else if` while
  `PSR2.ControlStructures.ElseIfDeclaration`, pulled in by the `PSR12` base standard, demands
  `elseif`, and the conflict left `phpcbf` unable to fix the file

### Fixed

- `has()`, `whereHas()`, `doesntHave()` and `withCount()` work over the `ancestors` and
  `descendants` relations: `BaseRelation` never implemented `getRelationExistenceQuery()`, so
  Laravel fell back to a key comparison and every such call died with
  `BadMethodCallException: … getExistenceCompareKey()`
- the existence condition for `ancestors` was a copy of the one for `descendants`, so it
  described descendants; it is now the mirror image, and both are scoped by tree, since roots
  all start at `lft = 1` and bounds overlap between trees
- `Migrate::dropColumns()` drops each index under the name `buildColumns()` created it with:
  the name was assembled in two places that had drifted apart, so rolling back a migration
  always failed with `index "…" does not exist` on the very first index
- `Collection::toTree()` and `toBreadcrumbs()` accept the starting key as a string: with an integer
  primary key a string key never matched the cast parent value, so the call silently returned an
  empty collection instead of the subtree
- `QueryBuilder\Fixing::columnPatch()` always signs the offset: `makeGap($cut, 0)` rendered
  `"lft"0` and failed as a SQL syntax error instead of behaving as the no-op it is
- `QueryBuilder\Fixing::columnPatch()` no longer carries an unreachable branch reached only when
  `$cut` was null, where `extract()` left five variables undefined and produced broken SQL
- Composer script `test:docker` now names the `app` service explicitly: without it `docker compose up`
  started `app` and `coverage` at once, both running the full suite against the same database and
  wiping each other in `setUp()`, so the script failed regardless of the state of the code
- Coding standard violations across `src/` and `tests/`, so `composer phpcs` passes: bracketed
  null-coalesce and unary operations, folded an adjacent string concat, moved a migration file
  docblock ahead of its imports, and wrapped three over-long lines
- Stale comment in `.docker/Dockerfile` claiming the default command is a `PHPCS + PHPUnit` gate,
  while `CMD` runs `composer test`

### Removed

- `fakerphp/faker` from `require-dev`: the removed `Structure` factories were its only consumer,
  and it still arrives transitively through `orchestra/testbench`
- Dead test fixtures `StructureHelper`, `StructureFactory` and `SoftDeleteStructureFactory`
  referencing the `Structure` model that was removed earlier, together with their `autoload-dev`
  PSR-4 mapping

## [6.1.0](https://github.com/efureev/laravel-trees/compare/v6.0.0...v6.1.0) (2026-06-05)

### Fixed

- `QueryBuilderV2::whereAncestorOf()` now supports an `or` boolean condition, fixing eager-loading of the
  `ancestors`/`descendants` relations (`Model::with('ancestors')` previously returned empty collections)
- `AncestorsRelation::matches()` corrected (an ancestor must contain the node within its bounds), so eager/`match`
  pairing of ancestors works for both single- and multi-trees without leaking across trees
- `Healthy\MissingParentCheck`: fixed infinite recursion (the `EXISTS` subquery now uses a dedicated builder instead of
  reusing the main query)
- `QueryBuilder\Fixing` repair logic is now operational again: `fixTree()`, `fixSubTree()`, `fixMultiTree()`,
  `reorderNodes()` — fixed enum attribute name cast, nullable parent in `reorderNodes()`, and multi-tree descendant
  scoping by `tree_id`

### Added

- Docker `coverage` service (bundled `pcov`) writing reports to `./storage/coverage`, plus composer script
  `test-cover:docker`
- Test coverage for previously untested areas: health checks (`Healthy/*`), tree fixing (`QueryBuilder\Fixing`),
  package exceptions (`Exceptions/*`), and ancestors/descendants relations (eager/lazy loading, multi-tree scoping)
- Multi-tree UUID support coverage: `MultiCategoryWithUuid` test model and `tests/Functional/Tree/Multi/Uuid/*`
  suite (basic/creation/deletion/movement/query-builder), mirroring the existing ULID multi-tree tests
- Multi-tree (base key) movement and deletion coverage: `tests/Functional/Tree/Multi/MoveTest.php` (within-tree,
  between-trees, sub-tree relocation) and `tests/Functional/Tree/Multi/DeleteTest.php` (root, leaf,
  cascade `deleteWithChildren`, move-children-to-parent)
- `UseConfigShorter` trait coverage: `tests/Unit/UseConfigShorterTest.php` (attribute/value shortcuts for uno &
  multi trees, `isRoot`/`isLevel`/`isEqualTo`/`getBounds`/`treeValue`, soft-delete config, and custom column
  names via `CustomColumnsCategory`)
- Move edge-case coverage: `tests/Functional/Tree/Uno/MoveEdgeCaseTest.php` and
  `tests/Functional/Tree/Multi/MoveEdgeCaseTest.php` (moving a node into its own descendant/self throws,
  no-op re-append keeps bounds stable, cross-tree sub-tree relocation propagates `tree_id`, back-and-forth
  moves keep bounds valid)

## [6.0.0](https://github.com/efureev/laravel-trees/compare/v5.4.0...v6.0.0) (2026-06-05)

### Changed

- Raised minimum requirements to `PHP >= 8.4` and `Laravel >= 13` (`illuminate/*: ^13.0`)
- Updated dev stack: `phpunit/phpunit: ^13.0`, `orchestra/testbench: ^11.0`, `phpstan/phpstan: ^2.2`,
  `efureev/support: ^5.0`
- Migrated PHPUnit configuration to the 13.0 schema; coverage artifacts moved to `./storage/coverage`
- Made `.phpcs.xml` compatible with `squizlabs/php_codesniffer` 4.0

### Added

- Docker-based test environment with PostgreSQL 18 (`docker-compose.yml`, `.docker/Dockerfile`, `.dockerignore`)
- Composer script `test:docker` to run the suite inside Docker

### Removed

- Dropped support for `Laravel 11/12` and `PHP 8.2/8.3`
- Removed obsolete Travis CI configuration (`.travis.yml`)
- Removed dead backward-compatibility check `method_exists($this, 'usesUniqueIds')` in `UseTree`

## [5.4.0](https://github.com/efureev/laravel-trees/compare/v5.3.0...v5.4.0) (2025-08-26)

### Added

- Method `parentsByModelId` for Query Builder. It allows you to get all parents of a model by its id (Without a main
  model). If you know `id` - you can select a list of parents. (Only 1 Query instead of 2)
- Method `columnWithTbl` for Query Builder. It allows you to get a column with a table name

## [5.3.0](https://github.com/efureev/laravel-trees/compare/v5.2.1...v5.3.0) (2025-03-08)

### Added

- Healthy Checkers

### Removed

- Remove `Healthy` trait

## [5.0.0-rc1](https://github.com/efureev/laravel-trees/compare/v4.0.0...v5.0.0-rc1) (2024-04-01)

### Added

- Full Code Refactoring
- Codebase has Break Changes
- Added `ULID` type for PK and TreeId

### Fixed

- Fixed hidden use cases
- [Delete Node] When deleting nodes with children (with the strategy of transferring children to the parent), all
  children-nodes were incorrectly updated, except for the first one
- Some fixed on softDelete

## [4.0.0](https://github.com/efureev/laravel-trees/compare/v3.8.2...v4.0.0) (2024-03-14)

### Added

- Added support `Laravel 11`
- Added support `PHP 8.3`

### Removed

- Removed support `Laravel 10.*`, `9.*`, `8.*```
- Removed support PHP `8.0`, `8.1`

## [3.8.2](https://github.com/efureev/laravel-trees/compare/v3.8.1...v3.8.2) (2023-09-11)

### Fixed

- On `DeleteWithChildren` in SoftDelete-models don't shifted offsets

## [3.8.1](https://github.com/efureev/laravel-trees/compare/v3.8.0...v3.8.1) (2023-08-19)

### Fixed

- Soft deleted models is now updating tree attributes (Before not)

## [3.8.0](https://github.com/efureev/laravel-trees/compare/v3.7.0...v3.8.0) (2023-03-20)

### Added

- Added support Laravel 10

## [3.7.0](https://github.com/efureev/laravel-trees/compare/v3.6.0...v3.7.0) (2022-08-17)

### Added

- Added support PHP `8.1`

## [3.6.0](https://github.com/efureev/laravel-trees/compare/v3.5.3...v3.6.0) (2022-04-27)

### Added

- Added restore with parents method

## [3.5.3](https://github.com/efureev/laravel-trees/compare/v3.5.2...v3.5.3) (2022-03-13)

### Added

- Support Laravel 9

## [3.5.2](https://github.com/efureev/laravel-trees/compare/v3.5.1...v3.5.2) (2022-02-03)

### Fixed

- You can append non-multi-tree-child to multi-tree-root
- Restore method

## [3.5.0](https://github.com/efureev/laravel-trees/compare/v3.4.1...v3.5.0) (2021-11-23)

### Fixed

- Now, models with `SoftDeletes` don't recalculate after deleting
- Fixed many bugs related to `Soft Delete` and `Restore`
