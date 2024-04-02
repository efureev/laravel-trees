<?php

declare(strict_types=1);

namespace Fureev\Trees\Exceptions;

use Illuminate\Database\Eloquent\Model;

class DeleteRootException extends Exception
{
    public function __construct(protected Model $model)
    {
        parent::__construct('Root node does not support delete action. #' . $this->model->getKey());
    }
}
