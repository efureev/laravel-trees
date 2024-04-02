<?php

declare(strict_types=1);

namespace Fureev\Trees\Exceptions;

use Illuminate\Database\Eloquent\Model;

class DeletedNodeHasChildrenException extends Exception
{
    public function __construct(protected Model $model)
    {
        parent::__construct('Deleted Node has children. #' . $this->model->getKey());
    }
}
