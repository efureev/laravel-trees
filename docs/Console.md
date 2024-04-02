# Console

![console tree](assets/table.png)

Build and draw table from the Tree:

```php
Table::fromModel($rootNode)->draw();
```

```php
$collection = Category::all();

Table::fromTree($collection->toTree())
    ->hideLevel()
    ->setExtraColumns(
        [
            'title'                         => 'Label',
            (string)$root->leftAttribute()  => 'Left',
            (string)$root->rightAttribute() => 'Right',
        ]
    )
    ->draw($output);
```
