<?php

declare(strict_types=1);

namespace Fureev\Trees\Tests\models\v5;

use Fureev\Trees\Strategy\DeleteStrategy;
use Fureev\Trees\Strategy\DeleteWithChildren;
use Illuminate\Database\Eloquent\Model;

/**
 * Records that it ran, then does what the default strategy does. Enough to prove
 * `setDeleterWithChildren()` is what `deleteWithChildren()` reaches for.
 */
class RecordingDeleter implements DeleteStrategy
{
    /** @var array<int, array{key: int|string|null, force: bool}> */
    public static array $calls = [];

    public function handle(Model $model, bool $forceDelete): mixed
    {
        static::$calls[] = [
            'key'   => $model->getKey(),
            'force' => $forceDelete,
        ];

        return (new DeleteWithChildren())->handle($model, $forceDelete);
    }
}
