# Changelog

## [unreleased]

### Added

- A logo for the package under `docs/assets/logo/`: `logo.svg` (horizontal lock-up, now at the
  top of `Readme.md`), `logo-icon.svg` (square mark), `logo-icon-small.svg` (simplified mark
  whose strokes survive 16 px), `logo-og.svg` (social card), plus rasterised `png/` sizes —
  favicons 16/32/48, app icons 64…1024, `apple-touch-icon.png`, README lock-up at 1x and 2x,
  `og-image.png` — and `favicon.ico` bundling 16/32/48
- `DocumentedPerformanceCountsTest`, one per tree shape: every number in the cost table of
  `docs/Performance.md` is now asserted from the query log, the way `DocumentedQueryCountsTest`
  already backs the "Queries" column of `docs/ApiReference.md`

### Fixed

- `docs/Performance.md` gave one number per operation where the cost depends on the case:
  inserting a root takes one statement or two, promoting a node to a root three or four, and a
  move costs five only when it moves — `up()` and `down()` cost six, a move to an adjacent spot
  four, and a re-position under the current parent writes nothing at all, because positioning
  changes no attribute and `save()` finds the model clean. The table now names the case,
  and a note lists the three ways a move departs from five
- `docs/Performance.md` said `toLevel($level)` cuts the range it reads; it adds a level
  predicate to a query and leaves the range alone

## [7.0.0](https://github.com/efureev/laravel-trees/compare/v6.1.0...v7.0.0) (2026-09-09)

A major, and a large one: an audit of the package produced this release almost in full. Most
entries are defects that were quietly wrong rather than loudly broken — a delete that re-parented
someone else's children, a bound shift that ran on the wrong connection, health checks that could
not be run on a real tree. It also raises the floor to PHP 8.5; Laravel stays at 13.

**What breaks is short**, and [MigrationGuide.md](./MigrationGuide.md) walks through it: three
signatures only reachable from inside the package, the counts `HealthyChecker` returns, the
order `Table::fromQuery()` expects its configuration in, and one import if you catch
`InvalidConfigException`. Everything else changes behaviour that
was wrong to begin with.

Test coverage over the same period went from 198 tests to 547, and from partial to complete —
every method and every line in `src/` is executed.

### Added

- `docs/Concepts.md`: what a nested set is, the vocabulary the rest of the documentation uses
  without re-explaining, when the shape fits and when a parent link would serve better, and what
  the package adds on top of the scheme
- `RangeCheck` and `RootCheck`, and `MissingParentCheck` joins the list `HealthyChecker` runs.
  Between them they close three blind spots: a tree whose numbering has holes because bounds
  were vacated and never reclaimed — which is what a subtree deleted by query leaves, and what
  every other check reads as perfectly nested; a second root, whose bounds need not collide with
  anything; and an orphan, which was already implemented but commented out of the list, so the
  documentation had to warn that an orphaned node reads as a healthy tree
- Composer script `gate` running PHPStan, PHPCS and PHPUnit in the order CI uses them, plus
  `gate:docker` and a matching `gate` service in `docker-compose.yml`, so the whole CI gate can be
  reproduced locally with one command
- Regression coverage for `QueryBuilder\Fixing::makeGap()` called with a zero offset
- `isDescendantOf()` and `isDirectChildOf()` on the tree trait. `isChildOf()` compares bounds,
  so it answers "is a descendant at any depth" rather than "is a child"; the first of the two is
  the same check under a name that says so, and the second is the one-level question the old name
  is mistaken for, answered from the parent column. `isChildOf()` is unchanged and stays
- `parentsByModelId()` works on single trees as well. It joins a subquery holding the target
  node and compares bounds, and the only thing a single tree lacked was something to join on —
  so the subquery is cross joined instead, which is the same thing without a condition. The
  method no longer raises `NotSupportedException`

### Changed

- Development dependencies sit on their current majors. `squizlabs/php_codesniffer` drops the
  `^3.11 ||` half of its constraint now that 4 is out, and `symfony/var-dumper` moves to `^8.0`
  — the old `^7.0` was the one thing holding a Symfony 7 package in a tree where everything else
  had moved to 8. The rest were already current. Checked in both resolutions CI uses: the
  highest, and `--prefer-lowest`, where the floors are testbench 11.0.0, PHPUnit 13.0.0, PHPStan
  2.2.0, CodeSniffer 4.0.2 and var-dumper 8.0.0
- `Migrate::columnsFromModel()` raises `Fureev\Trees\Exceptions\InvalidConfigException` rather
  than the one from `Php\Support`. Same short name, same message, so a `catch` block only needs
  its import changed — and it now sits under the package's own exception, so
  `catch (Fureev\Trees\Exceptions\Exception)` catches it too
- `Attribute::make()` declares its arguments: `make(AttributeType $name, FieldType $type =
  FieldType::UnsignedInteger)`. The trait it came from took `mixed ...$arguments`, so nothing
  described what an attribute is made of, to a reader or to static analysis. Existing calls are
  unaffected
- **PHP 8.5 is the minimum.** The constraint reads `^8.5`, where v6 asked for `>=8.4` — a floor
  one version lower, and no upper bound at all, so Composer would have installed the package on
  PHP 9 and anything after it. CI, the Docker image and the badges all say 8.5 now
- `Contracts\TreeModel` describes every method the tree traits add, rather than about a third of
  them. Code typed as `Model&TreeModel` — the delete strategies, the relations, the health
  checks — could not call the rest without static analysis objecting. What it leaves out on
  purpose are the methods the traits override rather than add (`getDirty()`, `newCollection()`,
  `newEloquentBuilder()`, `uniqueIds()`) along with the initialiser and booter Laravel calls on
  a trait: those belong to Eloquent, and an intersection with `Model` already covers them.

  Nothing implements the interface and nothing tests for it — `Helper::isTreeNode()` asks
  whether the model uses `UseTree` — so this changes no runtime behaviour and breaks no model.
  A test now fails if the contract and the traits drift apart again
- `Table::fromQuery()` is static, like `fromModel()` and `fromTree()` beside it. It was the odd
  one out, so the form written by analogy was a fatal error rather than a mistake with a
  message — and PHP refuses before any magic could soften it, since a public non-static method
  in a static context does not count as inaccessible and `__callStatic` never runs. Existing
  calls through an instance keep working; what no longer carries over is configuration applied
  before the call, because the table is built by the factory rather than configured in place
- `HealthyChecker::check()` returns six entries rather than three. Code comparing the whole
  array against a literal needs the new keys; `isBroken()` and `getTotalErrors()` are unaffected
- `DuplicatesCheck::check()` and `WrongParentCheck::check()` answer with a number of nodes, as
  the documentation always said they did. They used to count ordered pairs and (child, parent,
  intermediate) triples, so the same single defect scored higher on a bigger tree. Zero still
  means a healthy tree, and `isBroken()` is unaffected
- The `ancestors` and `descendants` relations come back in tree order on every path. Only one
  of the four was ordered before: `descendants` never was, and `ancestors` was ordered when
  read one node at a time but not when eager loaded — `whereAncestorOf()` applies the ordering
  to the builder `whereNested()` hands it, and that builder is discarded except for its wheres.
  `descendants` gains the ordering it never had, and both are now ordered once, outside the
  nested group, where it survives. `has()`, `whereHas()` and `withCount()` build their own
  subquery and are untouched
- Column names are taken as strings everywhere an `Attribute` object used to be handed over
  directly — `Collection::linkNodes()` grouping, the two subquery bounds in
  `parentsByModelId()`, and `byTree()`. Stringification produced the same name, so nothing
  changes, but the object took a route the string does not: `groupBy()` checks a non-string
  argument for `is_callable()` before treating it as a key, and an untyped `data_get()` is all
  that keeps the object from being a fatal
- CI job `lint` renamed to `Static Analysis & Coding Standards` and now runs `composer phpcs`
  next to `composer phpstan`, so the coding standard is enforced instead of merely declared
- `.phpcs.xml` no longer enforces member variable naming: Eloquent exposes database columns as
  snake_case properties (`$model->parent_id`, `$model->tree_id`), which are schema rather than style
- `.phpcs.xml` no longer requires a docblock on every property, joining the other
  `Squiz.Commenting.*` sniffs already disabled there
- `BaseRelation::relationExistenceCondition()` replaced by `addExistenceConstraint()`, which
  applies `whereColumn()` constraints instead of returning a raw SQL fragment. The method is
  protected and had no callers
- `UseNestedSet::shift()` moves both bounds in a single statement instead of one per column.
  Rows are picked by either bound and each column re-checks its own range in a `CASE`, so a row
  with both bounds in range — the common case — is written once rather than twice. Column names
  now go through the query grammar
- `DeleteWithChildren` asks for the trashed rows explicitly on a hard delete instead of relying
  on `Builder::forceDelete()` running on the raw query and skipping global scopes. Behaviour is
  unchanged, the dependency on a Laravel implementation detail is not
- `QueryBuilder\Fixing::makeGap()` declares `int` parameter types. No working call changes
  behaviour: any non-integer argument already produced invalid SQL rather than a query
- `.phpcs.xml` drops `Squiz.ControlStructures.ElseIfDeclaration`: it demands `else if` while
  `PSR2.ControlStructures.ElseIfDeclaration`, pulled in by the `PSR12` base standard, demands
  `elseif`, and the conflict left `phpcbf` unable to fix the file

### Removed

- The dependency on `efureev/support`. Three small things came from it — a `Maker` trait whose
  whole body is `new static(...$arguments)`, one exception class, and a global `instance()`
  helper — and carrying a package for them meant inheriting its release policy: its v6 requires
  PHP 8.5, which the tree has no use for. The package now requires nothing but
  `illuminate/database`, `illuminate/events` and `ext-pdo`
- The `forceSave` reset in `afterRestore()`, which only mopped up the stuck flag if the model
  happened to be restored afterwards. `forceSave()` now clears it on every exit
- `Table::getColumnNames()`, which forwarded to `getExtraColumnNames()` once its cache was
  taken out, and named itself wrongly besides: it returned the extra columns only, never the
  `level` and `ID` that `buildRowData()` adds. The remaining method pairs with
  `getExtraColumnLabel()`. Both are protected on a `final` class, so nothing outside the package
  could reach either
- Commented-out code that referenced methods which do not exist: a `throw` calling a factory
  `DeletedNodeHasChildrenException` never had, a call to an `onRestoredNode…` method absent from
  the whole package, and an abandoned `makeRoot()` signature taking a tree id the method does
  not accept
- `fakerphp/faker` from `require-dev`: the removed `Structure` factories were its only consumer,
  and it still arrives transitively through `orchestra/testbench`
- Dead test fixtures `StructureHelper`, `StructureFactory` and `SoftDeleteStructureFactory`
  referencing the `Structure` model that was removed earlier, together with their `autoload-dev`
  PSR-4 mapping

### Fixed

- `TreeModelContractTest` compares what a signature declares rather than what each side
  resolves it to. PHP 8.5 reports `self` in an interface as the interface's own name while a
  trait still reports the word, so the check went red on 8.5 over a return type nobody had
  touched
- Deleting a node no longer re-parents other people's children. The node's own children were
  found by level — every row in the tree sitting one below the deleted node's parent — so
  deleting a node two levels down handed the cousins to its parent while their bounds said they
  were somewhere else. They are matched by `parent_id` now
- A children handler that refuses a delete by throwing actually prevents it. The handler ran
  from `afterDelete()`, once the row was already gone, so `RefuseToOrphanChildren` announced a
  delete it had not stopped and left the children pointing at a row that no longer existed. It
  runs before the row is removed
- `moveChildrenToParent()` is correct on a node that stays. It shifted the children one to the
  left, which only adds up while the node is on its way out and something else is about to close
  the two bounds it held; called on a live node it left a child sharing a bound with its former
  parent. The children now move out to the place directly after the node, which becomes a leaf,
  and the tree keeps exactly the width it had
- `removeDescendants()` closes the bounds it frees. It deleted the rows and left the node as
  wide as the subtree it no longer had, so `isLeaf()` read `false` off the bounds with no
  children to show for it, and the vacated numbers were never reclaimed — a node appended
  afterwards opened a gap of its own and left the empty pair inside the parent for good. One
  shift now collapses the node's own right bound and moves everything after it up. On a
  soft-deleting model nothing shifts, because the rows are still there holding their place
- `fixTree()` places a node whose parent row is gone under the root of the tree it is
  repairing, and so does `fixMultiTree()`. Both used to hand such a node to a salvage pass that
  ran after the numbering and re-keyed one group at a time to `null`, then walked again with the
  original parent id — looking for a group it had just removed.

  On a whole single tree that made the node a second root, which the package refuses to write
  and no check could see until `RootCheck` existed. On a subtree, and therefore on every tree
  `fixMultiTree()` touches, the node was skipped instead: it kept its old bounds while
  everything around it was renumbered on top of them, so repairing a tree with one dangling link
  left it with five defects rather than one. Nodes caught in a cycle of parent links, which no
  dangling key reveals, are placed the same way
- The health checks run against the model they were handed. Both `HealthyChecker` and
  `AbstractCheck` reduced it to its class name and built a fresh one, discarding a connection
  chosen with `setConnection()` — so a tree on another connection was checked on the default one
  — and the attribute values that `getScopeAttributes()` narrows queries by, which left a scoped
  model checking against a scope of nulls
- `HealthyChecker` runs on a tree of any size. `DuplicatesCheck` cross joined the table with
  itself and compared four combinations of bounds with inequalities; `WrongParentCheck` joined
  three copies. No index helps such a join, so the cost was quadratic — 2.1 s at five thousand
  rows, 33.5 s at twenty thousand, and hours at a million, while the documentation presented the
  checker as the way to verify a tree. Both are now single passes that group and join on
  equality: 3 ms at five thousand rows, 8 ms at twenty thousand, 47 ms at a hundred thousand
- `WrongParentCheck` sees a broken link in a tree of two. It looked for a third row sitting
  between the child and the parent, so with no third row there was nothing to find. It now reads
  the same fact off the level, which also catches a corrupted `lvl` that the previous form could
  not see
- The bound shift runs on the node's own connection. It reached for its query through
  `Model::query()`, which is static and therefore builds a fresh instance carrying the default
  connection: the select and the insert went to the node's connection while the statement that
  rewrites the bounds of the whole tree went elsewhere. Anything using `Model::on()`,
  `setConnection()`, a tenant connection or a read/write split was writing half of each
  operation to the wrong database. It also made the advice in the readme untrue — wrapping a
  write in a transaction did not keep a second writer out, because the second writer's shift
  was not on the connection holding the locks
- `forceSave()` lowers its flag in a `finally`, so it lives exactly as long as the one save it
  was raised for. It was cleared on the `saved` and `updated` events only, and a listener
  vetoing `saving` makes `save()` return before either fires — the flag then stayed raised on
  the model for good, and every later plain `save()` wrote whether or not anything had changed.
  `up()` and `down()` go through the same helper
- `Table` renders its own columns. `getColumnNames()` memoised into a `static` declared inside
  the method, so the cache lived for the whole process rather than for the object: the second
  table built in one request took the first one's columns while showing its own headers, and
  the output was silently wrong rather than an error
- `isMulti()` reports how the model itself is configured. It used to answer about the node a
  pending `appendTo()` / `prependTo()` / `insertBefore()` / `insertAfter()` targeted, which is
  the same answer for two nodes of one class — the tree builder is static — and the wrong one
  for two classes over one table, where a single-tree model reported itself as multi-tree
  purely because of what it was being appended to. The documented meaning, "is the model
  configured with a tree column", is now what the code does. With that settled, the second half
  of the condition in `insertNode()` goes: it asked the same question twice
- `getPlainNodeData()` builds the positional bounds array from the configured column list
  instead of from the order the driver returned the columns in. It used to be
  `array_values()` over the fetched row, so the agreement between the two halves of
  `getNodeBounds()` — attributes for a model, a row for an id — rested on PostgreSQL happening
  to answer in `SELECT` order. Callers read that array by index, and nothing checked it
- `whereNodeBetween()` refuses a multi-tree call carrying nothing but a pair of bounds. It
  reads the tree value off the end of the array, so a two-element array had the right bound
  filtering as a tree id
- `defaultOrder()` no longer leaves the bindings of a raw ordering behind. It cleared the
  `orders` array by hand and nothing else, so a query built as
  `orderByRaw('... ?', [$v])->defaultOrder()` reached the database carrying a value with no
  placeholder left to fill — PostgreSQL answered `bind message supplies 1 parameters, but
  prepared statement requires 0`, and where the counts happened to match instead, every binding
  after it shifted by one. Reachable without naming the method at all: `parents()`,
  `parentsByModelId()`, `whereAncestorOf()` and the `children()` relation all call it. It now
  delegates to Laravel's `reorder()`, which drops the clause, its bindings and the union
  ordering together
- `moveChildrenToParent()` refuses a node with no parent instead of dying on it, and refuses
  before writing anything. It used to shift the descendants first and only then dereference the
  missing parent, so a root left the call with a raw `Error` and a renumbered tree behind it
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
- Promoting an existing node to a root now clears its `parent_id` and works through a plain
  `save()`. `moveNodeAsRoot()` moved the bounds, the level and the tree column but left the node
  pointing at its former parent in another tree, so `isRoot()` answered false and the `parent`
  relation led out of the tree. On top of that `makeRoot()` changed no attribute, so Eloquent
  saved the node as clean, skipped the update and never reached `afterUpdate()` — the whole
  operation was a silent no-op unless `forceSave()` was used
- `parentsByModelId()` raises the package's own `NotSupportedException` on a single tree instead
  of the global `\Exception`, so an application catching `Fureev\Trees\Exceptions\Exception`
  now catches it along with everything else. Not a breaking change: the new type descends from
  the old one, so any existing `catch (\Exception)` still matches
- Promoting a node into an explicitly chosen tree honours a tree id of `0`. `moveNodeAsRoot()`
  picked the requested id with `?:`, so a falsy one was replaced by a freshly generated id — and
  the generator never produces `0` itself, since it returns `max(tree_id) + 1`
- A pending operation is consumed by exactly one `save()`. The reset lived in `afterInsert()`
  and `afterUpdate()`, which hang off `created` and `updated` and only fire when a write
  happened, so a save that found nothing dirty left the operation armed and the next, unrelated
  save executed it — renaming a root could silently move it into a freshly generated tree
- The same operation on a single tree raises `Can not move a node as the root when Model is not
  set to "MultiTree"` instead of silently doing nothing: the exception was already there, but
  the skipped update meant `beforeUpdate()` never ran to throw it
- Column names in the raw SQL behind moving and deleting nodes go through the query grammar.
  PostgreSQL folds an unquoted identifier to lower case while the schema builder creates it
  quoted, so a model with a column named `leftBound` failed with `column "leftbound" does not
  exist` on every move, on deleting a branch and on `leaves()`. The tree column was raw in the
  `Duplicates` and `WrongParent` health checks too
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

- `Collection::fillMissingIntermediateNodes()`, and with it `toBreadcrumbs()`, fetches the
  missing ancestors of the whole collection in one query instead of one per node. Every node asks
  the same shape of question — which rows enclose its bounds — so they fold into a single `OR`
  group; for multi-trees the tree condition sits inside each group, since the nodes may come from
  different trees
- Deleting a node refreshes that node alone. `Model::refresh()` also reloads every relation that
  happens to be loaded, one query each, and `Collection::linkNodes()` leaves `children` — plus
  `parent` on request — set on every node it walks, so deleting a node taken from `toTree()` cost
  three queries where one was needed. Nothing was preserved by those reloads either: `refresh()`
  discarded the caller's relations and read them again. Where a relation is genuinely consulted —
  `isLeaf()` on a soft-deleting model — the count is unchanged
- Deleting a node no longer runs a `select count(*)` over its children. The answer went to
  `onDeletingNodeHasChildren()`, whose body is a commented-out throw, so nothing read it; where
  the model does not soft-delete, `isLeaf()` answers the same question from the bounds already in
  memory. `beforeDelete()` also refreshes first now, so both of its checks see current data
  rather than a stale model

### Fixed — documentation

- The "Queries" column of the API reference. Five of its numbers had drifted as the code under
  them changed: deleting a node with children costs seven statements rather than three, moving a
  sibling six rather than five, `saveAsRoot()` two rather than three, and
  `moveChildrenToParent()` four. `DocumentedQueryCountsTest` asserts every number in that column
  now, so a change in cost shows up as a failing test instead of a stale table
- The limitation about tree id types was missing from `Limitations.md` — `setTree()` stores the
  value as given while reading goes through the cast, so a string in an integer column compares
  unequal until the row is read back
- `getTreeConfig()`, `getTreeBuilder()`, `Table::setOutput()` and `Table::setCollection()` were
  in neither the reference nor its internal list; `initializeUseTree()` and `uniqueIds()` join
  the internal list
- The warning that `Fixing` is "not verified and tested on new Version 5" is gone from the docs
  and from the trait itself. It is tested now — a broken tree, a broken subtree, several trees
  at once, an orphan, a cycle of parent links, a tree with no root — and two defects in the
  repair were fixed in the course of writing those tests. It still rewrites every bound it
  touches, so the documentation calls it a last resort rather than untested
- The main example in `docs/AdvancedTreeConfig.md` did not parse: a missing semicolon after
  `setAttributes(...)`, plus `use Fureev\Trees\UseTree;` written inside the class body, which
  resolves against the current namespace and fails with `Trait "App\Models\Fureev\Trees\UseTree"
  not found`. All three examples in that file now carry proper imports
- The "chain of parent nodes in Laravel-Relation manner" section of `docs/ReceivingNodes.md`
  showed `$node->descendants` twice instead of `$node->ancestors`
- `parentByLevel()` was documented as returning a collection and as being equivalent to
  `parents($level)`; it returns a single model, `parents($level)->first()`
- `parentWithTrashed` was documented as a chain of parent nodes; it is a `BelongsTo`, one parent
- `docs/Helpers.md` listed `isSoftDelete()` as a public helper, but it is `protected`, so
  `$node->isSoftDelete()` falls through to Eloquent's `__call` and throws `BadMethodCallException`.
  The two working alternatives are documented instead. `parentValue()` was typed `?int` while it
  returns `int|string|null` for uuid and ulid keys
- Eleven query helpers in `docs/ReceivingNodes.md` had empty descriptions, `nextNodes()` among
  them, which also returns the node's own descendants
- `Migrate::dropColumns()` was not documented anywhere; `docs/Migration.md` now shows the rollback

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
