<?php

namespace Fureev\Trees\Exceptions;

use Illuminate\Database\Eloquent\Model;

/**
 * Class DeletedNodeHasChildrenException
 */
class DeletedNodeHasChildrenException extends Exception
{
    protected $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
        parent::__construct('Deleted Node has children. #' . $this->model->getKey());
    }
}
