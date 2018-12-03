<?php

namespace Fureev\Trees\Exceptions;

use Illuminate\Database\Eloquent\Model;

/**
 * Class UniqueRootException
 *
 * @package Php\Support\Exceptions
 */
class UniqueRootException extends Exception
{
    protected $existRootModel;

    public function __construct(Model $existRootModel)
    {
        $this->existRootModel = $existRootModel;
        parent::__construct('Can not create more than one root. Exist: #' . $this->existRootModel->getKey());
    }
}
