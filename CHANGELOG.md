# Changelog

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
