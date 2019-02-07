<?php

namespace Fureev\Trees\Exceptions;

use Illuminate\Database\Eloquent\Model;

/**
 * Class DeleteRootException
 *
 * @package Php\Support\Exceptions
 */
class DeleteRootException extends Exception
{
    protected $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
        parent::__construct('Root node does not support delete action. #' . $this->model->getKey());
    }
}
