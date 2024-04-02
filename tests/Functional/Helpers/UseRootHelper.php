<?php

namespace Fureev\Trees\Tests\Functional\Helpers;

use Fureev\Trees\Tests\models\BaseModel;

trait UseRootHelper
{
    protected static function createRoot(): BaseModel
    {
        $model = static::modelClass()(['title' => 'root node']);

        $model->makeRoot()->save();

        return $model;
    }
}
