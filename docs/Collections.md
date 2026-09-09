# Collections

Queries return `Fureev\Trees\Collection`, an Eloquent collection that knows the nodes are a tree.
Nothing here issues a query per node: the whole collection is turned into a tree in memory.

## Building a tree out of a flat result

```php
$collection = Category::query()->defaultOrder()->get();

$tree = $collection->toTree();
```

`toTree()` returns the **roots**, each with its `children` relation filled in, recursively. The
node count of the original result stays available:

```php
$tree->count();       // 1  — how many roots came back
$tree->totalCount();  // 4  — how many nodes went in
```

Pass a node, or its key, to start from somewhere other than the roots:

```php
$branch = $collection->toTree($node);        // direct children of $node, linked up
$branch = $collection->toTree($node->getKey());
```

The key may be given as a string: `toTree('5')` and `toTree(5)` mean the same thing.

Ask for the parent relation to be filled in as well when you plan to walk back up:

```php
$tree = $collection->toTree(setParentRelations: true);
```

## Roots of a partial result

```php
$roots = $collection->getRoots();
```

This filters by "has no parent", so on a partial result — say a search — it returns only genuine
roots, not the topmost nodes of the result.

## Breadcrumbs

Given a handful of deep nodes, `toBreadcrumbs()` pulls in the ancestors they are missing and then
links everything up, so the chain from the root down is walkable:

```php
$crumbs = Category::query()->where('title', 'Sneakers')->get()->toBreadcrumbs();

$root = $crumbs->first();
$root->children->first()->title;   // the next step down
```

The missing ancestors of the whole collection are fetched in a single query, however many nodes
are missing them.

## The steps on their own

`toBreadcrumbs()` is the two below, run in order. Call them directly when you want only one:

```php
$collection->fillMissingIntermediateNodes();  // add the ancestors that are absent
$collection->linkNodes();                     // fill in parent/children relations, no queries
```

`linkNodes()` is what `toTree()` uses internally, and it is safe to call twice — the second call
does nothing.
